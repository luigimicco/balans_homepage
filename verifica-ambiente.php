<?php
/**
 * File temporaneo di verifica: risponde alla domanda "su questo hosting si
 * puo' usare l'invio delle email di conferma?".
 *
 * Non serve nessuna password e nessun accesso al pannello OVH: basta
 * caricarlo via FTP nella stessa cartella di index.html e aprirlo nel
 * browser all'indirizzo:
 *
 *   https://www.balansapp.it/verifica-ambiente.php?chiave=c4d275b44099
 *
 * A verifica conclusa cancellalo dal server.
 */

// Chiave nell'indirizzo: senza, la pagina non risponde. Cosi' non resta
// esposto a chiunque il dettaglio della configurazione del server.
$CHIAVE = 'c4d275b44099';

if (!isset($_GET['chiave']) || !hash_equals($CHIAVE, (string) $_GET['chiave'])) {
    http_response_code(404);
    echo 'Not Found';
    exit;
}

$controlli = array();

// 1. Se stai leggendo questa pagina, PHP e' attivo.
$controlli[] = array(
    'label' => 'PHP attivo su questo hosting',
    'ok'    => version_compare(PHP_VERSION, '7.4', '>='),
    'note'  => 'versione ' . PHP_VERSION . ' — serve almeno la 7.4',
);

// 2. Necessaria per parlare in modo cifrato con il server di posta.
$controlli[] = array(
    'label' => 'Estensione OpenSSL',
    'ok'    => extension_loaded('openssl'),
    'note'  => extension_loaded('openssl') ? 'attiva' : 'assente: va chiesta a OVH',
);

// 3. Il punto piu' importante: l'hosting riesce a uscire verso il server
//    di posta OVH? Se entrambe le porte sono chiuse, l'invio non puo'
//    funzionare e serve un'altra strada.
foreach (array(587 => '', 465 => 'ssl://') as $porta => $prefisso) {
    $errno = 0;
    $errstr = '';
    $socket = @fsockopen($prefisso . 'ssl0.ovh.net', $porta, $errno, $errstr, 8);
    $aperta = (bool) $socket;
    if ($socket) {
        fclose($socket);
    }
    $controlli[] = array(
        'label' => 'Connessione a ssl0.ovh.net sulla porta ' . $porta,
        'ok'    => $aperta,
        'note'  => $aperta ? 'raggiungibile' : 'bloccata — ' . trim($errstr . ' (' . $errno . ')'),
    );
}

// 4. Serve per i contatori anti-abuso (non e' bloccante).
$prova = __DIR__ . '/.verifica-scrittura.tmp';
$scrivibile = @file_put_contents($prova, 'ok') !== false;
if ($scrivibile) {
    @unlink($prova);
}
$controlli[] = array(
    'label' => 'Scrittura file nella cartella del sito',
    'ok'    => $scrivibile,
    'note'  => $scrivibile ? 'consentita' : 'non consentita: i limiti anti-abuso non verranno applicati',
);

$tuttoOk = true;
foreach ($controlli as $c) {
    if (!$c['ok']) {
        $tuttoOk = false;
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Verifica ambiente — Balans</title>
<style>
  body { font: 15px/1.6 -apple-system, Segoe UI, Roboto, Arial, sans-serif; color: #1A1D24; background: #F4F7F6; margin: 0; padding: 32px 16px; }
  .wrap { max-width: 660px; margin: 0 auto; }
  .card { background: #fff; border: 1px solid #E6EBE9; border-radius: 14px; padding: 22px 24px; margin-bottom: 16px; }
  h1 { font-size: 20px; margin: 0 0 6px; }
  ul { list-style: none; margin: 0; padding: 0; }
  li { display: flex; gap: 12px; padding: 10px 0; border-bottom: 1px solid #F0F3F2; }
  li:last-child { border-bottom: 0; }
  .esito { flex: 0 0 20px; font-weight: 700; }
  .ok { color: #0E7A66; }
  .ko { color: #C0392B; }
  .note { color: #6B7080; font-size: 13.5px; }
  .esito-finale { border-left: 4px solid #1ABFA0; padding-left: 14px; }
  .esito-finale.no { border-left-color: #C0392B; }
  code { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 13px; }
</style>
</head>
<body>
<div class="wrap">

  <div class="card">
    <h1>Verifica ambiente</h1>
    <p class="note">Controlla se su questo hosting può funzionare l'invio dell'email di conferma agli iscritti.</p>
  </div>

  <div class="card">
    <ul>
      <?php foreach ($controlli as $c): ?>
      <li>
        <span class="esito <?php echo $c['ok'] ? 'ok' : 'ko'; ?>"><?php echo $c['ok'] ? '✓' : '✗'; ?></span>
        <span><?php echo htmlspecialchars($c['label'], ENT_QUOTES, 'UTF-8'); ?><br>
        <span class="note"><?php echo htmlspecialchars($c['note'], ENT_QUOTES, 'UTF-8'); ?></span></span>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>

  <div class="card">
    <div class="esito-finale <?php echo $tuttoOk ? '' : 'no'; ?>">
      <?php if ($tuttoOk): ?>
        <strong>Si può procedere.</strong><br>
        Manca solo una casella email sul dominio con la relativa password:
        va chiesta a chi ha l'accesso al Manager OVH.
      <?php else: ?>
        <strong>Qualcosa non torna.</strong><br>
        Manda uno screenshot di questa pagina a chi gestisce l'hosting:
        le voci rosse dicono esattamente cosa manca.
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <p class="note">Quando hai finito, cancella <code>verifica-ambiente.php</code> dal server via FTP.</p>
  </div>

</div>
</body>
</html>
