<?php
/**
 * Funzioni di supporto per le email che il sito manda a ogni iscrizione:
 * configurazione, controlli anti-abuso e spedizione via SMTP OVH.
 *
 * Questo file non risponde mai da solo: viene incluso da subscribe.php
 * e da diagnostica.php.
 */

if (!defined('BALANS_API')) {
    exit;
}

require_once __DIR__ . '/templates.php';
require_once __DIR__ . '/lib/PHPMailer/Exception.php';
require_once __DIR__ . '/lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/lib/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Carica config.php. Senza, l'endpoint non puo' fare nulla: ne' registrare
 * il contatto nello studio, ne' spedire le email.
 *
 * @return array|null
 */
function balans_load_config()
{
    $path = __DIR__ . '/config.php';
    if (!is_readable($path)) {
        return null;
    }

    $config = include $path;

    return is_array($config) ? $config : null;
}

/**
 * IP del chiamante. Su OVH il traffico non passa da un proxy nostro,
 * quindi REMOTE_ADDR e' il dato affidabile.
 */
function balans_client_ip()
{
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
}

/**
 * Accetta la richiesta solo se arriva da una pagina del sito.
 * Non e' una barriera crittografica, ma taglia fuori gli script banali
 * che provassero a usare l'endpoint per spedire mail a terzi.
 */
function balans_origin_allowed($config)
{
    $allowed = isset($config['allowed_origins']) ? $config['allowed_origins'] : array();

    // Prove in locale: se il sito stesso sta girando su localhost accettiamo
    // anche le sue pagine, senza dover modificare config.php avanti e indietro.
    // In produzione HTTP_HOST e' balansapp.it, quindi questo ramo non si attiva
    // mai e nessuno puo' spacciarsi per localhost dall'esterno.
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    $nomeHost = strtok($host, ':');
    if ($nomeHost === 'localhost' || $nomeHost === '127.0.0.1' || $nomeHost === '[::1]') {
        $allowed[] = 'http://' . $host;
        $allowed[] = 'https://' . $host;
    }

    if (!empty($_SERVER['HTTP_ORIGIN'])) {
        return in_array($_SERVER['HTTP_ORIGIN'], $allowed, true);
    }

    // Alcuni browser omettono Origin sulle richieste same-origin: ripiego su Referer.
    if (!empty($_SERVER['HTTP_REFERER'])) {
        $parts = parse_url($_SERVER['HTTP_REFERER']);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }
        $origin = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }
        return in_array($origin, $allowed, true);
    }

    return false;
}

/**
 * Cartella dove tenere i contatori anti-abuso.
 * Prima prova dentro /api (protetta da .htaccess), poi la temp di sistema.
 */
function balans_data_dir()
{
    $local = __DIR__ . '/.data';

    if (is_dir($local) || @mkdir($local, 0700, true)) {
        if (is_writable($local)) {
            return $local;
        }
    }

    return rtrim(sys_get_temp_dir(), '/') . '/balans-mail';
}

function balans_counter_path($prefix, $key)
{
    $dir = balans_data_dir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }

    return $dir . '/' . $prefix . '-' . sha1(strtolower($key)) . '.txt';
}

/**
 * Elimina i contatori piu' vecchi di 48 ore. Girando solo una volta ogni
 * 50 richieste circa, non pesa sul tempo di risposta.
 */
function balans_gc_counters()
{
    if (mt_rand(1, 50) !== 1) {
        return;
    }

    $dir = balans_data_dir();
    $files = @glob($dir . '/*.txt');
    if (!$files) {
        return;
    }

    $limit = time() - 172800;
    foreach ($files as $file) {
        if (@filemtime($file) < $limit) {
            @unlink($file);
        }
    }
}

/**
 * Limite di invii per IP nell'ultima ora.
 *
 * @return bool true se la richiesta puo' proseguire
 */
function balans_ip_quota_ok($config, $ip)
{
    $max = isset($config['max_per_ip_hour']) ? (int) $config['max_per_ip_hour'] : 12;
    if ($max <= 0) {
        return true;
    }

    $path = balans_counter_path('ip', $ip);
    $now = time();
    $count = 0;
    $windowStart = $now;

    $raw = @file_get_contents($path);
    if ($raw !== false) {
        $parts = explode('|', trim($raw));
        if (count($parts) === 2) {
            $windowStart = (int) $parts[0];
            $count = (int) $parts[1];
        }
    }

    if ($now - $windowStart > 3600) {
        $windowStart = $now;
        $count = 0;
    }

    if ($count >= $max) {
        return false;
    }

    @file_put_contents($path, $windowStart . '|' . ($count + 1), LOCK_EX);

    return true;
}

/**
 * Evita di riscrivere allo stesso indirizzo se il form viene inviato
 * due volte di fila (doppio click, ricaricamento della pagina).
 *
 * Il contatore e' separato per tipo di richiesta: chi si iscrive alla
 * waitlist e poi chiede la demo con la stessa email deve ricevere
 * entrambe le conferme.
 *
 * Il contatore viene solo letto: a segnarlo e' balans_email_mark_sent(), che
 * il chiamante invoca quando l'iscrizione e' stata davvero presa in carico.
 * Segnarlo qui vorrebbe dire bruciare il turno anche a una richiesta che poi
 * viene respinta dalla quota per IP: l'utente vedrebbe un errore e, al
 * tentativo successivo, un finto "andato tutto bene" senza che parta nulla.
 *
 * @param string $type 'waitlist' | 'demo', gia' validato dal chiamante
 * @return bool true se all'indirizzo si puo' scrivere adesso
 */
function balans_email_not_recent($config, $email, $type)
{
    $hours = isset($config['dedupe_hours']) ? (int) $config['dedupe_hours'] : 24;
    if ($hours <= 0) {
        return true;
    }

    $path = balans_counter_path('mail', $type . '|' . $email);
    $last = (int) @file_get_contents($path);

    return !($last > 0 && (time() - $last) < ($hours * 3600));
}

/**
 * Segna che a questo indirizzo, per questo form, e' appena stato scritto.
 * Da chiamare solo dopo aver superato tutti i controlli.
 */
function balans_email_mark_sent($config, $email, $type)
{
    $hours = isset($config['dedupe_hours']) ? (int) $config['dedupe_hours'] : 24;
    if ($hours <= 0) {
        return;
    }

    @file_put_contents(balans_counter_path('mail', $type . '|' . $email), time(), LOCK_EX);
}

/**
 * PHPMailer gia' agganciato all'SMTP di OVH, senza destinatario ne' corpo.
 * Condiviso dalle due email che il sito manda: la conferma all'iscritto e
 * la notifica al team.
 *
 * @return PHPMailer
 */
function balans_mailer($config)
{
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host       = $config['smtp_host'];
    $mail->Port       = (int) $config['smtp_port'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $config['smtp_user'];
    $mail->Password   = $config['smtp_pass'];
    $secure = isset($config['smtp_secure']) ? $config['smtp_secure'] : 'tls';
    if ($secure === 'none') {
        // Solo per prove in locale: in produzione va sempre usato tls o ssl.
        $mail->SMTPSecure  = '';
        $mail->SMTPAutoTLS = false;
        $mail->SMTPAuth    = !empty($config['smtp_user']);
    } else {
        $mail->SMTPSecure = $secure === 'ssl'
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
    }
    $mail->Timeout    = 15;
    $mail->CharSet    = 'UTF-8';
    $mail->Encoding   = 'base64';

    $mail->setFrom($config['from_email'], $config['from_name']);
    $mail->isHTML(true);

    return $mail;
}

/**
 * Costruisce e spedisce l'email di conferma all'iscritto.
 *
 * @param array  $config
 * @param string $email  destinatario, gia' validato
 * @param string $type   'waitlist' | 'demo'
 * @param string $name   nome dell'iscritto, gia' ripulito; '' se non disponibile
 * @return array array('ok' => bool, 'error' => string)
 */
function balans_send_confirmation($config, $email, $type, $name = '')
{
    $content = balans_email_content($type, $name);
    if ($content === null) {
        return array('ok' => false, 'error' => 'Tipo di messaggio sconosciuto: ' . $type);
    }

    $mail = null;

    try {
        $mail = balans_mailer($config);

        if (!empty($config['reply_to'])) {
            $mail->addReplyTo($config['reply_to'], $config['from_name']);
        }
        $mail->addAddress($email);

        $mail->Subject = $content['subject'];
        $mail->Body    = balans_email_layout($content);
        $mail->AltBody = balans_email_plaintext($content);

        $mail->send();

        return array('ok' => true, 'error' => '');
    } catch (PHPMailerException $e) {
        return array('ok' => false, 'error' => $mail === null ? $e->getMessage() : $mail->ErrorInfo);
    } catch (Exception $e) {
        return array('ok' => false, 'error' => $e->getMessage());
    }
}

/**
 * Avvisa il team che e' arrivata una nuova iscrizione.
 *
 * Prima lo faceva Web3Forms. Adesso questa email e' anche la copia di
 * riserva dell'iscrizione: se la registrazione nello studio non e' andata a
 * buon fine, e' l'unico posto dove quei dati restano scritti.
 *
 * Il Reply-To e' l'indirizzo dell'iscritto, cosi' si puo' rispondere
 * direttamente dalla notifica.
 *
 * @param array $dati   array('type','email','name','quando','origine','consenso')
 * @param array $studio esito di balans_studio_create_contact()
 * @return array array('ok' => bool, 'error' => string)
 */
function balans_send_notification($config, $dati, $studio)
{
    $destinatario = isset($config['notify_email']) ? trim((string) $config['notify_email']) : '';
    if ($destinatario === '') {
        $destinatario = isset($config['from_email']) ? (string) $config['from_email'] : '';
    }

    if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
        return array('ok' => false, 'error' => 'notify_email non configurato o non valido.');
    }

    $n = balans_internal_notification($dati, $studio);
    $mail = null;

    try {
        $mail = balans_mailer($config);
        $mail->addAddress($destinatario);
        $mail->addReplyTo($dati['email'], $dati['name'] !== '' ? $dati['name'] : $dati['email']);

        $mail->Subject = $n['subject'];
        $mail->Body    = balans_internal_layout($n);
        $mail->AltBody = balans_internal_plaintext($n);

        $mail->send();

        return array('ok' => true, 'error' => '');
    } catch (PHPMailerException $e) {
        return array('ok' => false, 'error' => $mail === null ? $e->getMessage() : $mail->ErrorInfo);
    } catch (Exception $e) {
        return array('ok' => false, 'error' => $e->getMessage());
    }
}

/**
 * Riga di log in /api/.data/log.txt: serve solo a capire, in caso di
 * segnalazione, se la mail e' partita. Nessun contenuto del messaggio.
 */
function balans_log($line)
{
    $path = balans_data_dir() . '/log.txt';
    $entry = date('c') . ' ' . $line . "\n";
    @file_put_contents($path, $entry, FILE_APPEND | LOCK_EX);
}
