<?php
/**
 * Client per l'API esterna di StudioGestione.
 *
 * Registra in anagrafica chi si iscrive dal sito, al posto di Web3Forms.
 * Documentazione: https://studio.luigimicco.it/docs/api
 * Contratto completo (OpenAPI 3.0.3): /docs/api.json
 *
 * Di tutta l'API serve una sola operazione:
 *
 *   POST /contacts   {"name": "...", "email": "...", "notes": "..."}
 *   -> 201  {"success": true, "data": {"id": 123, ...}}
 *
 * La chiave sta in config.php e non esce mai dal server: e' il motivo per
 * cui il form del sito non parla piu' direttamente con un servizio esterno.
 *
 * Questo file non risponde mai da solo: viene incluso da subscribe.php
 * e da diagnostica.php.
 */

if (!defined('BALANS_API')) {
    exit;
}

/** Etichetta leggibile del tipo di richiesta, usata in notes e nelle email. */
function balans_studio_etichetta($type)
{
    return $type === 'demo'
        ? 'Richiesta demo — Balans Business'
        : 'Waiting list — Balans';
}

/**
 * Nome da mandare all'API.
 *
 * 'name' e' l'unico campo obbligatorio di POST /contacts: se arrivasse vuoto
 * la chiamata fallirebbe con 422 e il contatto andrebbe perso. Meglio un
 * ripiego imperfetto che un'iscrizione buttata via, quindi si scende a
 * cascata: nome scritto dall'utente -> parte prima della @ -> etichetta fissa.
 *
 * @param string $name  gia' ripulito dal chiamante; '' se non disponibile
 * @param string $email indirizzo gia' validato
 * @param string $type  'waitlist' | 'demo'
 */
function balans_studio_nome($name, $email, $type)
{
    if ($name !== '') {
        return mb_substr($name, 0, 255, 'UTF-8');
    }

    $locale = trim((string) strstr($email, '@', true));
    if ($locale !== '') {
        // "mario.rossi" -> "Mario Rossi": non e' il nome vero, ma in rubrica
        // e' piu' leggibile di una stringa tutta minuscola con i punti.
        $locale = str_replace(array('.', '_', '-', '+'), ' ', $locale);
        $locale = trim(preg_replace('/\s+/', ' ', $locale));
        if ($locale !== '' && !ctype_digit($locale)) {
            return mb_substr(mb_convert_case($locale, MB_CASE_TITLE, 'UTF-8'), 0, 255, 'UTF-8');
        }
    }

    return $type === 'demo' ? 'Richiesta demo (senza nome)' : 'Iscritto waiting list (senza nome)';
}

/**
 * Costruisce il corpo di POST /contacts.
 *
 * Vengono valorizzati solo i campi che il form raccoglie davvero: phone,
 * address, codice_fiscale e partita_iva restano fuori, e custom_fields non
 * viene mai inviato finche' non sappiamo quali campi esistono per lo studio
 * (un nome sconosciuto fa fallire tutta la chiamata con 422).
 *
 * @param array $dati array('type','email','name','consenso','origine','quando')
 * @return array pronto per json_encode
 */
function balans_studio_payload($dati)
{
    $type = $dati['type'];

    $notes = array(
        balans_studio_etichetta($type),
        'Iscrizione dal sito del ' . $dati['quando'],
    );

    if (!empty($dati['origine'])) {
        $notes[] = 'Pagina: ' . $dati['origine'];
    }

    $notes[] = $dati['consenso'];

    return array(
        'name'  => balans_studio_nome($dati['name'], $dati['email'], $type),
        'email' => mb_substr($dati['email'], 0, 255, 'UTF-8'),
        'notes' => implode("\n", $notes),
    );
}

/**
 * Crea il contatto nello studio.
 *
 * Non lancia mai eccezioni e non interrompe mai il flusso: qualunque cosa
 * vada storta viene restituita al chiamante, che la registra nel log e la
 * riporta nella notifica interna. L'iscrizione resta comunque valida.
 *
 * @return array array(
 *     'ok'      => bool,
 *     'id'      => int|null,   id del contatto creato
 *     'status'  => int,        codice HTTP (0 se la richiesta non e' partita)
 *     'error'   => string,     messaggio leggibile, '' se ok
 *     'skipped' => string,     '' oppure il motivo per cui non si e' inviato
 *     'payload' => array,      quello che e' stato (o sarebbe stato) inviato
 * )
 */
function balans_studio_create_contact($config, $dati)
{
    $payload = balans_studio_payload($dati);

    $esito = array(
        'ok'      => false,
        'id'      => null,
        'status'  => 0,
        'error'   => '',
        'skipped' => '',
        'payload' => $payload,
    );

    if (empty($config['studio_api_enabled'])) {
        $esito['skipped'] = 'integrazione disattivata in config.php (studio_api_enabled)';
        return $esito;
    }

    $key = isset($config['studio_api_key']) ? trim((string) $config['studio_api_key']) : '';
    if ($key === '' || $key === 'INSERISCI-LA-CHIAVE-API') {
        $esito['error'] = 'Chiave API non configurata.';
        return $esito;
    }

    // Dry run: si costruisce tutto e ci si ferma un istante prima di partire.
    // Serve a provare form, anti-abuso ed email senza creare record veri.
    if (!empty($config['studio_api_dry_run'])) {
        $esito['skipped'] = 'dry run attivo (studio_api_dry_run)';
        return $esito;
    }

    $base = rtrim(isset($config['studio_api_base']) ? (string) $config['studio_api_base'] : '', '/');
    if ($base === '') {
        $esito['error'] = 'Indirizzo dell\'API non configurato.';
        return $esito;
    }

    $timeout = isset($config['studio_api_timeout']) ? (int) $config['studio_api_timeout'] : 6;
    if ($timeout <= 0) {
        $timeout = 6;
    }

    $risposta = balans_studio_request('POST', $base . '/contacts', $payload, $key, $timeout);

    $esito['status'] = $risposta['status'];

    if ($risposta['error'] !== '') {
        $esito['error'] = $risposta['error'];
        return $esito;
    }

    $corpo = json_decode($risposta['body'], true);

    if ($risposta['status'] === 201 || $risposta['status'] === 200) {
        $esito['ok'] = true;
        if (isset($corpo['data']['id'])) {
            $esito['id'] = (int) $corpo['data']['id'];
        }
        return $esito;
    }

    $esito['error'] = balans_studio_messaggio_errore($risposta['status'], $corpo, $risposta['body']);

    return $esito;
}

/**
 * Traduce una risposta di errore in una riga leggibile da chi apre la mail.
 * L'API risponde {"success":false,"message":"...","code":N,"errors":...}.
 * Attenzione: 'errors' torna come array vuoto quando non ci sono dettagli,
 * quindi non si puo' dare per scontato che sia una mappa campo => messaggi.
 */
function balans_studio_messaggio_errore($status, $corpo, $grezzo)
{
    $spiegazioni = array(
        401 => 'chiave API mancante o non valida',
        403 => 'la chiave non ha il permesso di creare contatti',
        404 => 'endpoint inesistente: controllare studio_api_base',
        422 => 'dati rifiutati dallo studio',
        429 => 'troppe richieste: limite della chiave superato',
    );

    $riga = 'HTTP ' . $status;
    if (isset($spiegazioni[$status])) {
        $riga .= ' (' . $spiegazioni[$status] . ')';
    }

    if (is_array($corpo) && !empty($corpo['message'])) {
        $riga .= ' — ' . (string) $corpo['message'];
    } elseif (!is_array($corpo)) {
        // Risposta non JSON: capita con le pagine di errore del server web.
        $riga .= ' — risposta non interpretabile: ' . substr(trim(strip_tags((string) $grezzo)), 0, 200);
    }

    // Dettaglio per campo, presente di norma solo sui 422.
    if (is_array($corpo) && !empty($corpo['errors']) && is_array($corpo['errors'])) {
        $dettagli = array();
        foreach ($corpo['errors'] as $campo => $messaggi) {
            $testo = is_array($messaggi) ? implode(' ', $messaggi) : (string) $messaggi;
            $dettagli[] = (is_string($campo) ? $campo . ': ' : '') . $testo;
        }
        if ($dettagli) {
            $riga .= ' [' . implode('; ', $dettagli) . ']';
        }
    }

    return $riga;
}

/**
 * Verifica che la chiave funzioni, senza scrivere niente.
 *
 * Fa una GET /contacts?ipp=1: legge il primo contatto e non ne crea nessuno.
 * Serve alla pagina di diagnostica per distinguere "chiave sbagliata" da
 * "studio irraggiungibile" prima ancora che qualcuno provi a iscriversi.
 *
 * Ignora di proposito studio_api_dry_run: e' una lettura, non un invio.
 *
 * @return array array('ok' => bool, 'status' => int, 'error' => string)
 */
function balans_studio_ping($config)
{
    $esito = array('ok' => false, 'status' => 0, 'error' => '');

    $key = isset($config['studio_api_key']) ? trim((string) $config['studio_api_key']) : '';
    if ($key === '' || $key === 'INSERISCI-LA-CHIAVE-API') {
        $esito['error'] = 'Chiave API non configurata.';
        return $esito;
    }

    $base = rtrim(isset($config['studio_api_base']) ? (string) $config['studio_api_base'] : '', '/');
    if ($base === '') {
        $esito['error'] = 'Indirizzo dell\'API non configurato.';
        return $esito;
    }

    $timeout = isset($config['studio_api_timeout']) ? (int) $config['studio_api_timeout'] : 6;
    if ($timeout <= 0) {
        $timeout = 6;
    }

    $risposta = balans_studio_request('GET', $base . '/contacts?ipp=1', null, $key, $timeout);
    $esito['status'] = $risposta['status'];

    if ($risposta['error'] !== '') {
        $esito['error'] = $risposta['error'];
        return $esito;
    }

    if ($risposta['status'] === 200) {
        $esito['ok'] = true;
        return $esito;
    }

    // Un 403 qui significa che la chiave puo' scrivere ma non leggere: la
    // registrazione dei contatti puo' comunque funzionare.
    $esito['error'] = balans_studio_messaggio_errore(
        $risposta['status'],
        json_decode($risposta['body'], true),
        $risposta['body']
    );

    return $esito;
}

/**
 * Richiesta HTTP autenticata verso l'API.
 *
 * Usa curl quando c'e', altrimenti ripiega sugli stream di PHP: sugli
 * hosting condivisi una delle due strade e' sempre disponibile.
 *
 * @param string     $method  'GET' oppure 'POST'
 * @param array|null $payload corpo JSON; null per le richieste senza corpo
 * @return array array('status' => int, 'body' => string, 'error' => string)
 */
function balans_studio_request($method, $url, $payload, $key, $timeout)
{
    $json = '';
    if ($payload !== null) {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return array('status' => 0, 'body' => '', 'error' => 'Payload non codificabile in JSON.');
        }
    }

    $headers = array(
        'Authorization: Bearer ' . $key,
        'Accept: application/json',
    );
    if ($payload !== null) {
        $headers[] = 'Content-Type: application/json';
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_POSTFIELDS     => $payload === null ? null : $json,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
        ));

        $body = curl_exec($ch);
        $errore = ($body === false) ? curl_error($ch) : '';
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return array(
            'status' => $status,
            'body'   => (string) $body,
            'error'  => $errore === '' ? '' : 'Connessione non riuscita: ' . $errore,
        );
    }

    if (!ini_get('allow_url_fopen')) {
        return array(
            'status' => 0,
            'body'   => '',
            'error'  => 'Nessun modo per uscire in HTTPS: servono l\'estensione curl oppure allow_url_fopen.',
        );
    }

    $contesto = stream_context_create(array(
        'http' => array(
            'method'        => $method,
            'header'        => implode("\r\n", $headers),
            'content'       => $json,
            'timeout'       => $timeout,
            // Senza questo un 4xx farebbe restituire false e perderemmo il
            // corpo della risposta, che e' proprio quello che ci serve leggere.
            'ignore_errors' => true,
        ),
        'ssl' => array(
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ),
    ));

    $body = @file_get_contents($url, false, $contesto);

    $status = 0;
    if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
        $status = (int) $m[1];
    }

    if ($body === false && $status === 0) {
        return array('status' => 0, 'body' => '', 'error' => 'Connessione non riuscita.');
    }

    return array('status' => $status, 'body' => (string) $body, 'error' => '');
}
