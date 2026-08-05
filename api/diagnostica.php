<?php
/**
 * Pagina di verifica: serve una volta sola, dopo il caricamento via FTP.
 *
 *   https://www.balansapp.it/api/diagnostica.php?token=IL-TUO-TOKEN
 *
 * Parametri opzionali:
 *   &anteprima=waitlist     mostra l'email cosi' come la vedra' l'utente
 *   &prova=tua@email.it     spedisce davvero l'email di prova a quell'indirizzo
 *   &tipo=demo              usa il testo della demo invece della waiting list
 *
 * Senza token la pagina non risponde. A verifica conclusa puoi anche
 * cancellare questo file dal server: il resto continua a funzionare.
 */

define('BALANS_API', true);

require_once __DIR__ . '/mailer.php';

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

// --- Anteprima dell'email nel browser ------------------------------------
if (!empty($_GET['anteprima'])) {
    header('Content-Type: text/html; charset=utf-8');
    echo balans_email_layout(balans_email_content($tipo));
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
<title>Diagnostica invio email — Balans</title>
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
    <h1>Diagnostica invio email</h1>
    <p class="note">Se tutte le voci sono verdi, l'email di conferma agli iscritti funziona.</p>
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

  <div class="card">
    <h2>Prova pratica</h2>
    <?php if ($esitoProva === null): ?>
      <p>Aggiungi <code>&amp;prova=tua@email.it</code> all'indirizzo di questa pagina per ricevere davvero l'email di prova.<br>
      Con <code>&amp;tipo=demo</code> usi il testo della richiesta demo invece di quello della waiting list.</p>
      <p><a href="?token=<?php echo urlencode($token); ?>&amp;anteprima=1&amp;tipo=<?php echo esc($tipo); ?>">Apri l'anteprima dell'email (<?php echo esc($tipo); ?>)</a></p>
    <?php elseif ($esitoProva['ok']): ?>
      <p class="ok"><strong>✓ Email inviata a <?php echo esc($esitoProva['to']); ?></strong></p>
      <p class="note">Controlla la posta in arrivo e, se non la trovi, la cartella spam.</p>
    <?php else: ?>
      <p class="ko"><strong>✗ Invio non riuscito</strong></p>
      <pre><?php echo esc($esitoProva['error']); ?></pre>
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
