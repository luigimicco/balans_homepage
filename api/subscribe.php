<?php
/**
 * Endpoint delle iscrizioni: waiting list e richieste demo.
 *
 * Prende il posto di Web3Forms. Il form del sito manda i dati qui e basta;
 * da qui in poi fa tutto il server, che e' anche l'unico posto dove la
 * chiave dell'API dello studio puo' stare senza finire in mano a chiunque
 * apra il sorgente della pagina.
 *
 * Richiesta:  POST /api/subscribe.php
 *             {"email": "...", "type": "waitlist"|"demo",
 *              "nome": "...", "consenso_privacy": "accettato"}
 * Risposta:   {"success": true} oppure {"success": false, "message": "..."}
 *
 * In ordine:
 *   1. controlli (origine, honeypot, email, tipo, consenso, doppioni, quota)
 *   2. POST /contacts sull'API dello studio
 *   3. notifica a info@balansapp.it, con l'esito del passo 2
 *   4. email di conferma all'iscritto
 *
 * I passi 2, 3 e 4 non possono far fallire l'iscrizione: se qualcosa va
 * storto viene registrato nel log e riportato nella notifica interna, ma
 * l'utente vede comunque la conferma. L'unica cosa che gli si puo' chiedere
 * di correggere sono i dati che ha scritto lui.
 */

define('BALANS_API', true);

require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/studio-client.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

/**
 * @param int    $status codice HTTP
 * @param bool   $ok
 * @param string $message
 */
function balans_respond($status, $ok, $message = '')
{
    http_response_code($status);
    echo json_encode(array('success' => $ok, 'message' => $message));
    exit;
}

/**
 * Chiude la risposta verso il browser e lascia proseguire lo script.
 *
 * Le due email richiedono ciascuna un dialogo con il server SMTP: senza
 * questo, il pulsante del form resterebbe su "Invio in corso..." per tutto
 * il tempo. Dove la funzione non esiste (php -S, mod_php) non succede nulla
 * di male: si continua e la connessione si chiude a fine script.
 */
function balans_flush_response($status, $ok, $message = '')
{
    http_response_code($status);
    echo json_encode(array('success' => $ok, 'message' => $message));

    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
        return;
    }

    if (function_exists('litespeed_finish_request')) {
        litespeed_finish_request();
        return;
    }

    @ob_end_flush();
    @flush();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    balans_respond(405, false, 'Metodo non consentito.');
}

$config = balans_load_config();
if ($config === null) {
    balans_log('ERRORE config.php mancante o illeggibile');
    balans_respond(500, false, 'Servizio non configurato.');
}

if (!balans_origin_allowed($config)) {
    balans_respond(403, false, 'Richiesta non autorizzata.');
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    balans_respond(400, false, 'Dati non validi.');
}

/** Legge un campo del form come stringa ripulita dagli spazi ai bordi. */
function campo($payload, $chiave)
{
    return isset($payload[$chiave]) ? trim((string) $payload[$chiave]) : '';
}

// --- Honeypot -------------------------------------------------------------
// Il campo 'botcheck' e' invisibile nel form: un utente non lo compila mai.
// Finora a scartarlo era Web3Forms. Rispondiamo che e' andato tutto bene
// senza fare nulla: un bot che riceve un errore riprova, uno che riceve un
// "ok" se ne va.
if (campo($payload, 'botcheck') !== '') {
    balans_log('BOT scartato');
    balans_respond(200, true);
}

$email = campo($payload, 'email');
$type  = campo($payload, 'type');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
    balans_respond(400, false, 'Indirizzo email non valido.');
}

if (!in_array($type, array('waitlist', 'demo'), true)) {
    balans_respond(400, false, 'Tipo di richiesta non valido.');
}

// --- Consenso privacy -----------------------------------------------------
// La checkbox esiste solo nel form della waiting list, quindi e' li' che va
// pretesa. Per la demo si registra che il form non la prevede, cosi' nella
// notifica e in anagrafica resta scritto cosa e' stato effettivamente
// raccolto e cosa no.
$consensoDato = campo($payload, 'consenso_privacy') !== '';

if ($type === 'waitlist' && !$consensoDato) {
    balans_respond(400, false, 'Serve accettare la Privacy Policy.');
}

$consenso = $consensoDato
    ? 'Privacy Policy accettata al momento dell\'invio'
    : 'Consenso non richiesto da questo form';

// --- Nome -----------------------------------------------------------------
// Serve a due cose diverse: il saluto dell'email di conferma (dove un nome
// storto e' solo brutto) e il campo 'name' dell'API, che e' obbligatorio.
// Qui si ripulisce e basta; il ripiego per l'API lo decide studio-client.php.
$name = campo($payload, 'nome');
if ($name !== '') {
    // Con /u su UTF-8 non valido preg_replace restituisce null: in quel caso
    // il nome sparisce, che e' esattamente il comportamento voluto.
    $name = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $name);
    $name = function_exists('mb_substr')
        ? mb_substr($name, 0, 60, 'UTF-8')
        : substr($name, 0, 60);
    $name = trim($name);
}

balans_gc_counters();

// Doppio invio ravvicinato dello stesso form: e' gia' stato fatto tutto.
// Il controllo viene prima della quota per IP cosi' un doppio click non
// consuma i tentativi disponibili.
if (!balans_email_not_recent($config, $email, $type)) {
    balans_log('DEDUPE type=' . $type . ' to=' . substr(sha1($email), 0, 12));
    balans_respond(200, true);
}

if (!balans_ip_quota_ok($config, balans_client_ip())) {
    balans_log('LIMITE ip=' . sha1(balans_client_ip()) . ' type=' . $type);
    balans_respond(429, false, 'Troppe richieste, riprova piu\' tardi.');
}

// --- Da qui in poi l'iscrizione e' valida ---------------------------------

balans_email_mark_sent($config, $email, $type);

$origine = '';
if (!empty($_SERVER['HTTP_REFERER'])) {
    $parts = parse_url($_SERVER['HTTP_REFERER']);
    if (!empty($parts['host'])) {
        $origine = $parts['host'] . (isset($parts['path']) ? $parts['path'] : '');
    }
}

$dati = array(
    'type'     => $type,
    'email'    => $email,
    'name'     => $name,
    'quando'   => date('d/m/Y H:i'),
    'origine'  => $origine,
    'consenso' => $consenso,
);

// 1. Registrazione nello studio. Non blocca mai: l'esito viaggia nella
//    notifica interna, che parte comunque.
$studio = balans_studio_create_contact($config, $dati);

$logStudio = 'STUDIO ' . ($studio['ok'] ? 'OK id=' . $studio['id'] : ($studio['skipped'] !== '' ? 'SKIP ' . $studio['skipped'] : 'KO ' . $studio['error']));
balans_log($logStudio . ' type=' . $type . ' to=' . substr(sha1($email), 0, 12));

if ($studio['skipped'] !== '') {
    // In dry run serve vedere cosa sarebbe partito, senza che parta.
    balans_log('PAYLOAD ' . json_encode($studio['payload'], JSON_UNESCAPED_UNICODE));
}

// L'utente puo' andare avanti: quello che resta sono due email, e nessuna
// delle due cambia l'esito della sua iscrizione.
balans_flush_response(200, true);

// 2. Notifica al team, con l'esito del passo 1. Va spedita prima della
//    conferma di cortesia: se l'hosting esaurisce la quota giornaliera di
//    invii, a restare fuori dev'essere la seconda, non questa.
$notifica = balans_send_notification($config, $dati, $studio);
balans_log('NOTIFICA ' . ($notifica['ok'] ? 'OK' : 'KO ' . str_replace("\n", ' ', $notifica['error']))
    . ' type=' . $type);

// 3. Conferma all'iscritto.
$conferma = balans_send_confirmation($config, $email, $type, $name);
balans_log('CONFERMA ' . ($conferma['ok'] ? 'OK' : 'KO ' . str_replace("\n", ' ', $conferma['error']))
    . ' type=' . $type . ' to=' . substr(sha1($email), 0, 12));
