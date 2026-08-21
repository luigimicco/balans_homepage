<?php
/**
 * Pagina di verifica: serve una volta sola, dopo il caricamento via FTP.
 *
 *   https://www.balansapp.it/api/diagnostica.php?token=IL-TUO-TOKEN
 *
 * Parametri opzionali:
 *   &anteprima=1            mostra l'email cosi' come la vedra' l'utente
 *   &payload=1              mostra il JSON che verrebbe mandato allo studio
 *   &api=1                  prova la chiave con una lettura (non crea nulla)
 *   &prova=tua@email.it     spedisce davvero l'email di prova a quell'indirizzo
 *   &notifica=tua@email.it  spedisce a quell'indirizzo la notifica interna
 *   &tipo=demo              usa la demo invece della waiting list
 *
 * Nessuno di questi crea contatti nello studio: &api=1 e' una sola lettura,
 * &payload=1 non invia niente. L'unico modo per creare un contatto vero e'
 * mettere studio_api_dry_run a false e compilare il form dal sito.
 *
 * Senza token la pagina non risponde. A verifica conclusa puoi anche
 * cancellare questo file dal server: il resto continua a funzionare.
 */

define('BALANS_API', true);

require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/studio-client.php';

$config = balans_load_config();

// Nessun config, nessun token: la pagina non deve nemmeno esistere.
$token = isset($_GET['token']) ? (string) $_GET['token'] : '';
$expected = ($config !== null && !empty($config['diagnostics_token'])) ? $config['diagnostics_token'] : '';

if ($expected === '' || $token === '' || !hash_equals($expected, $token)) {
    http_response_code(404);
    echo 'Not Found';
    exit;
}

$tipo = (isset($_GET['tipo']) && $_GET['tipo'] === 'demo') ? 'demo' : 'waitlist';

/** Iscrizione finta, usata dalle anteprime e dagli invii di prova. */
function balans_dati_prova($tipo, $email = 'prova@example.it')
{
    return array(
        'type'     => $tipo,
        'email'    => $email,
        'name'     => 'Mario Rossi',
        'quando'   => date('d/m/Y H:i'),
        'origine'  => 'www.balansapp.it' . ($tipo === 'demo' ? '/business/' : '/'),
        'consenso' => $tipo === 'waitlist'
            ? 'Privacy Policy accettata al momento dell\'invio'
            : 'Consenso non richiesto da questo form',
    );
}

// --- Anteprima dell'email nel browser ------------------------------------
if (!empty($_GET['anteprima'])) {
    header('Content-Type: text/html; charset=utf-8');
    echo balans_email_layout(balans_email_content($tipo));
    exit;
}

// --- Anteprima della notifica interna ------------------------------------
if (!empty($_GET['anteprima_notifica'])) {
    header('Content-Type: text/html; charset=utf-8');
    $dati = balans_dati_prova($tipo);
    echo balans_internal_layout(balans_internal_notification(
        $dati,
        balans_studio_create_contact($config, $dati)
    ));
    exit;
}

// --- Payload che verrebbe mandato allo studio ----------------------------
// Non parte nulla: si vede solo il JSON, per controllare che rispetti lo
// schema dell'API (name presente, lunghezze nei limiti, notes leggibile).
if (!empty($_GET['payload'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        balans_studio_payload(balans_dati_prova($tipo)),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

header('Content-Type: text/html; charset=utf-8');

$checks = array();

// --- Controlli di base ----------------------------------------------------
$checks[] = array(
    'label' => 'Versione PHP',
    'ok'    => version_compare(PHP_VERSION, '7.4', '>='),
    'note'  => PHP_VERSION . ' (serve almeno la 7.4)',
);

$checks[] = array(
    'label' => 'Estensione OpenSSL (connessione cifrata a OVH)',
    'ok'    => extension_loaded('openssl'),
    'note'  => extension_loaded('openssl') ? 'attiva' : 'assente: chiedi a OVH di attivarla',
);

$checks[] = array(
    'label' => 'File config.php',
    'ok'    => true,
    'note'  => 'letto correttamente',
);

$campiObbligatori = array('smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'from_email');
$mancanti = array();
foreach ($campiObbligatori as $campo) {
    if (empty($config[$campo])) {
        $mancanti[] = $campo;
    }
}
$checks[] = array(
    'label' => 'Campi della configurazione',
    'ok'    => empty($mancanti),
    'note'  => empty($mancanti) ? 'tutti valorizzati' : 'da compilare: ' . implode(', ', $mancanti),
);

/** Legge una chiave della configurazione senza generare warning se assente. */
function cfg($config, $chiave)
{
    return isset($config[$chiave]) ? (string) $config[$chiave] : '';
}

$checks[] = array(
    'label' => 'Mittente coerente con la casella SMTP',
    'ok'    => cfg($config, 'from_email') !== '' && strcasecmp(cfg($config, 'from_email'), cfg($config, 'smtp_user')) === 0,
    'note'  => 'from_email deve essere identico a smtp_user, altrimenti OVH rifiuta l\'invio',
);

$dataDir = balans_data_dir();
$scrivibile = is_dir($dataDir) ? is_writable($dataDir) : false;
$checks[] = array(
    'label' => 'Cartella dei contatori anti-abuso',
    'ok'    => $scrivibile,
    'note'  => $dataDir . ($scrivibile ? ' (scrivibile)' : ' (non scrivibile: i limiti non verranno applicati)'),
);

$checks[] = array(
    'label' => 'Credenziali di esempio sostituite',
    'ok'    => cfg($config, 'diagnostics_token') !== 'CAMBIA-QUESTA-STRINGA'
               && cfg($config, 'smtp_pass') !== 'INSERISCI-LA-PASSWORD',
    'note'  => 'i valori di esempio di config.example.php vanno sostituiti',
);

// --- StudioGestione -------------------------------------------------------
$notifica = cfg($config, 'notify_email');
$checks[] = array(
    'label' => 'Indirizzo che riceve le notifiche',
    'ok'    => filter_var($notifica, FILTER_VALIDATE_EMAIL) !== false,
    'note'  => $notifica !== ''
        ? $notifica . ' — riceve un avviso a ogni nuova iscrizione'
        : 'notify_email non compilato: le notifiche andrebbero a from_email',
);

$usciteHttps = function_exists('curl_init') || ini_get('allow_url_fopen');
$checks[] = array(
    'label' => 'Chiamate in uscita verso l\'API dello studio',
    'ok'    => $usciteHttps,
    'note'  => function_exists('curl_init')
        ? 'estensione curl attiva'
        : ($usciteHttps
            ? 'curl assente, si usa allow_url_fopen (funziona lo stesso)'
            : 'ne curl ne allow_url_fopen: chiedi a OVH di attivare curl'),
);

$chiaveApi = cfg($config, 'studio_api_key');
$chiaveOk = $chiaveApi !== '' && $chiaveApi !== 'INSERISCI-LA-CHIAVE-API';
$checks[] = array(
    'label' => 'Chiave API di StudioGestione',
    'ok'    => $chiaveOk,
    'note'  => $chiaveOk
        ? 'presente (' . substr($chiaveApi, 0, 8) . '…' . substr($chiaveApi, -3) . ')'
        : 'da chiedere al responsabile dello studio e incollare in config.php',
);

$dryRun = !empty($config['studio_api_dry_run']);
$abilitata = !empty($config['studio_api_enabled']);
$checks[] = array(
    'label' => 'Registrazione dei contatti nello studio',
    // Non e' un errore in nessuno dei due stati: e' l'interruttore che si
    // gira alla fine, quando tutto il resto e' stato verificato.
    'ok'    => $abilitata,
    'note'  => !$abilitata
        ? 'disattivata (studio_api_enabled = false): nessun contatto viene registrato'
        : ($dryRun
            ? 'PROVA A VUOTO — i contatti NON vengono registrati. Metti studio_api_dry_run a false per attivare davvero.'
            : 'attiva: ogni iscrizione crea un contatto nello studio'),
);

// --- Connessione al server SMTP ------------------------------------------
$smtpErrore = '';
$smtpOk = false;
$porta = (int) $config['smtp_port'];
$secure = isset($config['smtp_secure']) ? $config['smtp_secure'] : 'tls';
$host = ($secure === 'ssl' ? 'ssl://' : '') . $config['smtp_host'];

$socket = @fsockopen($host, $porta, $errno, $errstr, 10);
if ($socket) {
    $smtpOk = true;
    fclose($socket);
} else {
    $smtpErrore = trim($errstr . ' (' . $errno . ')');
}

$checks[] = array(
    'label' => 'Connessione a ' . $config['smtp_host'] . ':' . $porta,
    'ok'    => $smtpOk,
    'note'  => $smtpOk ? 'raggiungibile' : 'non raggiungibile — ' . $smtpErrore,
);

// --- Prova della chiave API, in sola lettura ------------------------------
// GET /contacts?ipp=1: legge il primo contatto, non ne crea nessuno.
$esitoApi = null;
if (!empty($_GET['api'])) {
    $esitoApi = balans_studio_ping($config);
}

// --- Invio di prova -------------------------------------------------------
$esitoProva = null;
if (!empty($_GET['prova'])) {
    $destinatario = trim((string) $_GET['prova']);
    if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
        $esitoProva = array('ok' => false, 'error' => 'Indirizzo non valido: ' . $destinatario);
    } else {
        $esitoProva = balans_send_confirmation($config, $destinatario, $tipo);
        $esitoProva['to'] = $destinatario;
    }
}

// --- Prova della notifica interna ----------------------------------------
// Spedita a un indirizzo scelto qui, non a notify_email: cosi' si vede com'e'
// fatta senza riempire di prove la casella del team.
$esitoNotifica = null;
if (!empty($_GET['notifica'])) {
    $destinatario = trim((string) $_GET['notifica']);
    if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
        $esitoNotifica = array('ok' => false, 'error' => 'Indirizzo non valido: ' . $destinatario);
    } else {
        $dati = balans_dati_prova($tipo, 'prova@example.it');
        $esitoNotifica = balans_send_notification(
            array('notify_email' => $destinatario) + $config,
            $dati,
            balans_studio_create_contact($config, $dati)
        );
        $esitoNotifica['to'] = $destinatario;
    }
}

// --- Ultime righe di log --------------------------------------------------
$log = @file_get_contents($dataDir . '/log.txt');
$ultimeRighe = array();
if ($log) {
    $righe = array_values(array_filter(explode("\n", trim($log))));
    $ultimeRighe = array_slice($righe, -15);
}

function esc($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Diagnostica iscrizioni — Balans</title>
<style>
  body { font: 15px/1.6 -apple-system, Segoe UI, Roboto, Arial, sans-serif; color: #1A1D24; background: #F4F7F6; margin: 0; padding: 32px 16px; }
  .wrap { max-width: 720px; margin: 0 auto; }
  .card { background: #fff; border: 1px solid #E6EBE9; border-radius: 14px; padding: 24px 26px; margin-bottom: 18px; }
  h1 { font-size: 21px; margin: 0 0 4px; }
  h2 { font-size: 15px; margin: 0 0 12px; text-transform: uppercase; letter-spacing: .04em; color: #6B7080; }
  ul { list-style: none; margin: 0; padding: 0; }
  li { display: flex; gap: 12px; padding: 10px 0; border-bottom: 1px solid #F0F3F2; }
  li:last-child { border-bottom: 0; }
  .esito { flex: 0 0 22px; font-weight: 700; }
  .ok { color: #0E7A66; }
  .ko { color: #C0392B; }
  .note { color: #6B7080; font-size: 13.5px; }
  code, pre { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 13px; }
  pre { background: #F7F9F8; border: 1px solid #E6EBE9; border-radius: 8px; padding: 12px; overflow-x: auto; }
  a { color: #0E7A66; }
  .avviso { background: #FDF4E7; border: 1px solid #F3D9AE; border-radius: 10px; padding: 12px 14px; font-size: 13.5px; }
</style>
</head>
<body>
<div class="wrap">

  <div class="card">
    <h1>Diagnostica iscrizioni</h1>
    <p class="note">Se tutte le voci sono verdi, le iscrizioni dal sito vengono registrate nello studio e
    le due email &mdash; la notifica al team e la conferma all'iscritto &mdash; partono.</p>
  </div>

  <div class="card">
    <h2>Controlli</h2>
    <ul>
      <?php foreach ($checks as $c): ?>
      <li>
        <span class="esito <?php echo $c['ok'] ? 'ok' : 'ko'; ?>"><?php echo $c['ok'] ? '✓' : '✗'; ?></span>
        <span><?php echo esc($c['label']); ?><br><span class="note"><?php echo esc($c['note']); ?></span></span>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>

  <?php $q = '?token=' . urlencode($token) . '&amp;tipo=' . esc($tipo); ?>

  <div class="card">
    <h2>StudioGestione</h2>
    <?php if ($esitoApi === null): ?>
      <p>Aggiungi <code>&amp;api=1</code> all'indirizzo di questa pagina per provare la chiave con una lettura.
      Serve solo a sapere se la chiave è valida: <strong>non crea nessun contatto</strong>.</p>
      <p><a href="<?php echo $q; ?>&amp;api=1">Prova la chiave adesso (sola lettura)</a></p>
    <?php elseif ($esitoApi['ok']): ?>
      <p class="ok"><strong>✓ La chiave funziona</strong></p>
      <p class="note">Lettura riuscita: l'API risponde e la chiave è valida.</p>
    <?php else: ?>
      <p class="ko"><strong>✗ La chiave non ha risposto come previsto</strong></p>
      <pre><?php echo esc($esitoApi['error']); ?></pre>
      <p class="note">Un <code>403</code> qui significa che la chiave può scrivere ma non leggere: la registrazione
      delle iscrizioni può funzionare lo stesso. Un <code>401</code> significa invece chiave sbagliata.</p>
    <?php endif; ?>
    <p><a href="<?php echo $q; ?>&amp;payload=1">Vedi il JSON che verrebbe inviato (<?php echo esc($tipo); ?>)</a></p>
  </div>

  <div class="card">
    <h2>Prova pratica</h2>
    <?php if ($esitoProva === null && $esitoNotifica === null): ?>
      <p>Aggiungi <code>&amp;prova=tua@email.it</code> all'indirizzo di questa pagina per ricevere davvero l'email
      di conferma, oppure <code>&amp;notifica=tua@email.it</code> per ricevere la notifica interna, quella che
      arriva a <?php echo esc($notifica !== '' ? $notifica : 'info@balansapp.it'); ?> a ogni iscrizione.<br>
      Con <code>&amp;tipo=demo</code> usi la richiesta demo invece della waiting list.</p>
      <p><a href="<?php echo $q; ?>&amp;anteprima=1">Anteprima dell'email all'iscritto (<?php echo esc($tipo); ?>)</a><br>
      <a href="<?php echo $q; ?>&amp;anteprima_notifica=1">Anteprima della notifica interna (<?php echo esc($tipo); ?>)</a></p>
    <?php endif; ?>

    <?php if ($esitoProva !== null): ?>
      <?php if ($esitoProva['ok']): ?>
        <p class="ok"><strong>✓ Email di conferma inviata a <?php echo esc($esitoProva['to']); ?></strong></p>
        <p class="note">Controlla la posta in arrivo e, se non la trovi, la cartella spam.</p>
      <?php else: ?>
        <p class="ko"><strong>✗ Invio della conferma non riuscito</strong></p>
        <pre><?php echo esc($esitoProva['error']); ?></pre>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($esitoNotifica !== null): ?>
      <?php if ($esitoNotifica['ok']): ?>
        <p class="ok"><strong>✓ Notifica interna inviata a <?php echo esc($esitoNotifica['to']); ?></strong></p>
        <p class="note">È la stessa email che riceverà il team a ogni nuova iscrizione.</p>
      <?php else: ?>
        <p class="ko"><strong>✗ Invio della notifica non riuscito</strong></p>
        <pre><?php echo esc($esitoNotifica['error']); ?></pre>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>Ultimi invii registrati</h2>
    <?php if ($ultimeRighe): ?>
      <pre><?php echo esc(implode("\n", $ultimeRighe)); ?></pre>
      <p class="note">Gli indirizzi non vengono mai scritti in chiaro: <code>to=</code> è un'impronta anonima.</p>
    <?php else: ?>
      <p class="note">Nessun invio registrato finora.</p>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="avviso">Quando la verifica è conclusa, cancella pure <code>diagnostica.php</code> dal server via FTP: l'invio delle email continua a funzionare.</div>
  </div>

</div>
</body>
</html>
