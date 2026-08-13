<?php
/**
 * Endpoint che invia all'utente l'email di conferma dell'iscrizione.
 *
 * Viene chiamato da assets/js/script.js subito dopo che Web3Forms ha
 * accettato l'invio del form: Web3Forms continua a notificare noi,
 * questo script scrive all'utente.
 *
 * Richiesta:  POST /api/send-confirmation.php
 *             {"email": "...", "type": "waitlist"|"demo", "name": "..."}
 *             'name' e' facoltativo: se manca la mail parte col saluto neutro.
 * Risposta:   {"success": true} oppure {"success": false, "message": "..."}
 *
 * Non espone mai il motivo tecnico dell'errore: l'iscrizione e' comunque
 * andata a buon fine e l'utente non deve vedere messaggi allarmanti.
 */

define('BALANS_API', true);

require_once __DIR__ . '/mailer.php';

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

$email = isset($payload['email']) ? trim((string) $payload['email']) : '';
$type  = isset($payload['type']) ? (string) $payload['type'] : '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
    balans_respond(400, false, 'Indirizzo email non valido.');
}

if (!in_array($type, array('waitlist', 'demo'), true)) {
    balans_respond(400, false, 'Tipo di richiesta non valido.');
}

// Il nome serve solo a personalizzare il saluto: se arriva vuoto, malformato
// o con caratteri di controllo lo scartiamo e proseguiamo. L'iscrizione su
// Web3Forms e' gia' andata a buon fine, non e' un motivo per rispondere 400.
$name = isset($payload['name']) ? trim((string) $payload['name']) : '';
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

// Doppio invio ravvicinato dello stesso form: rispondiamo ok, la mail e'
// gia' stata spedita. Il controllo viene prima della quota per IP cosi' un
// doppio click non consuma i tentativi disponibili.
if (!balans_email_not_recent($config, $email, $type)) {
    balans_log('DEDUPE type=' . $type . ' to=' . substr(sha1($email), 0, 12));
    balans_respond(200, true, 'Email gia\' inviata di recente.');
}

if (!balans_ip_quota_ok($config, balans_client_ip())) {
    balans_log('LIMITE ip=' . sha1(balans_client_ip()) . ' type=' . $type);
    balans_respond(429, false, 'Troppe richieste, riprova piu\' tardi.');
}

$result = balans_send_confirmation($config, $email, $type, $name);

// Nel log finisce solo l'impronta dell'indirizzo, mai l'indirizzo in chiaro.
balans_log(($result['ok'] ? 'OK' : 'KO') . ' type=' . $type . ' to=' . substr(sha1($email), 0, 12)
    . ($result['ok'] ? '' : ' err=' . str_replace("\n", ' ', $result['error'])));

if (!$result['ok']) {
    balans_respond(502, false, 'Invio non riuscito.');
}

balans_respond(200, true);
