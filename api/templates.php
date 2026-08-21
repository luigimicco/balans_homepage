<?php
/**
 * Testi e layout delle email di conferma.
 *
 * Per cambiare il contenuto di una mail modifica solo l'array in
 * balans_email_content(): oggetto, titolo, paragrafi, testo del bottone.
 * Il layout HTML (balans_email_layout) di norma non va toccato.
 *
 * Nei testi si possono usare tag HTML semplici, per esempio <br> per andare
 * a capo e <b> per il grassetto. Fanno eccezione 'subject' e 'heading', che
 * sono sempre trattati come testo semplice.
 */

if (!defined('BALANS_API')) {
    exit;
}

define('BALANS_SITE_URL', 'https://www.balansapp.it');
define('BALANS_LOGO_URL', BALANS_SITE_URL . '/assets/img/logos/logo-azzurro-full.png');
define('BALANS_PRIVACY_URL', BALANS_SITE_URL . '/privacy/');

/**
 * Contenuti delle due email, per tipo di form.
 *
 * @param string $type 'waitlist' oppure 'demo'
 * @param string $name nome dell'iscritto; se vuoto si usa il saluto neutro
 * @return array|null  null se il tipo non esiste
 */
function balans_email_content($type, $name = '')
{
    // Il nome arriva da un form pubblico e i testi qui sotto ammettono HTML:
    // va sempre escapato, altrimenti finisce interpretato nel corpo della mail.
    $saluto = $name !== ''
        ? 'Ciao ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ','
        : 'Ciao,';

    $contents = array(

        'waitlist' => array(
            'subject'  => 'Confermata l\'iscrizione alla waiting list',
            'preview'  => 'Da oggi sei tra le prime persone che verranno invitate a provare l\'app.',
            'heading'  => 'Sei in lista!',
            'intro'    => $saluto . ' grazie per la tua iscrizione alla waiting list di Balans! Da oggi sei tra le prime persone che verranno invitate a provare l\'app.',
            'paragraphs' => array(
                array(
                    'tipo'  => 'lista',
                    'testo' => 'Con Balans potrai:',
                    'voci'  => array(
                        'creare fatture parlando in linguaggio naturale;',
                        'gestire scontrini e spese senza perdere tempo;',
                        'ricevere promemoria automatici sulle scadenze.',
                    ),
                ),
                array('tipo' => 'sottotitolo', 'testo' => 'Cosa succede adesso?'),
                'Nei prossimi mesi apriremo gli accessi a piccoli gruppi. Quando arriverà il tuo turno riceverai una mail con il link per attivare il tuo account.',
                'Essere tra i primi iscritti significa avere priorità di accesso e ricevere eventuali vantaggi riservati ai beta tester.',
                'Nel frattempo stiamo lavorando per rendere la gestione della Partita IVA semplice quanto inviare un messaggio.',
            ),
            // Senza 'cta_label' e 'cta_url' il pulsante non viene mostrato.
            'signature' => 'A presto,<br><b>il Team Balans</b>',
            'footer_reason' => 'Ricevi questa email perché hai richiesto di entrare nella waiting list su balansapp.it.',
        ),

        'demo' => array(
            'subject'  => 'La tua richiesta di demo è arrivata',
            'preview'  => 'Ti ricontattiamo per organizzare insieme la demo di Balans Business.',
            'heading'  => 'Ti ricontattiamo a breve',
            'intro'    => $saluto . ' abbiamo ricevuto la tua richiesta di demo per Balans Business.',
            'paragraphs' => array(
                'Ti scriviamo a questo indirizzo per organizzare la demo, nel giorno e nell\'orario che preferisci.',
                'Con Balans vedrai i tuoi clienti in un\'unica dashboard, la chat privata con ciascuno di loro e l\'app che useranno con il logo del tuo studio. Onboarding e formazione sono inclusi.',
            ),
            'cta_label' => 'Vai a Balans Business',
            'cta_url'   => BALANS_SITE_URL . '/business/',
            'footer_reason' => 'Ricevi questa email perché hai richiesto una demo di Balans Business su balansapp.it.',
        ),
    );

    return isset($contents[$type]) ? $contents[$type] : null;
}

/**
 * Trasforma gli elementi di 'paragraphs' in HTML.
 *
 * Ogni elemento puo' essere:
 *  - una stringa, e diventa un paragrafo (il caso piu' comune);
 *  - array('tipo' => 'sottotitolo', 'testo' => '...');
 *  - array('tipo' => 'lista', 'testo' => 'riga introduttiva', 'voci' => array(...)).
 */
function balans_email_blocchi($blocchi)
{
    $stilePar = 'margin:0 0 16px;font-size:15px;line-height:1.65;color:#393E4A;';
    $html = '';

    foreach ($blocchi as $b) {
        if (!is_array($b)) {
            $html .= '<p style="' . $stilePar . '">' . $b . '</p>';
            continue;
        }

        $tipo = isset($b['tipo']) ? $b['tipo'] : '';

        if ($tipo === 'sottotitolo') {
            $html .= '<p style="margin:26px 0 12px;font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:bold;line-height:1.4;color:#1A1D24;">'
                . $b['testo'] . '</p>';
        } elseif ($tipo === 'lista') {
            if (!empty($b['testo'])) {
                $html .= '<p style="margin:0 0 8px;font-size:15px;line-height:1.65;color:#393E4A;">' . $b['testo'] . '</p>';
            }
            $voci = '';
            foreach ($b['voci'] as $v) {
                $voci .= '<li style="margin:0 0 6px;">' . $v . '</li>';
            }
            $html .= '<ul style="margin:0 0 16px;padding-left:22px;font-size:15px;line-height:1.65;color:#393E4A;">' . $voci . '</ul>';
        }
    }

    return $html;
}

/**
 * Versione HTML dell'email: tabelle e stili inline, gli unici che i client
 * di posta (Outlook e Gmail in testa) rendono in modo affidabile.
 */
function balans_email_layout($c)
{
    $paragraphs = balans_email_blocchi($c['paragraphs']);

    // Il pulsante compare solo se il testo prevede 'cta_label' e 'cta_url'.
    $cta = '';
    if (!empty($c['cta_label']) && !empty($c['cta_url'])) {
        $cta = '<tr>
      <td align="center" style="padding:12px 32px ' . (empty($c['signature']) ? '32px' : '26px') . ';">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
          <tr><td align="center" style="background:#1ABFA0;border-radius:999px;">
            <a href="' . $c['cta_url'] . '" style="display:inline-block;padding:14px 30px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:bold;color:#FFFFFF;text-decoration:none;">' . htmlspecialchars($c['cta_label'], ENT_QUOTES, 'UTF-8') . '</a>
          </td></tr>
        </table>
      </td>
    </tr>';
    }

    // La firma sta in fondo, in una riga a parte. Senza pulsante sopra le
    // serve un po' di respiro in piu' rispetto all'ultimo paragrafo.
    $firma = '';
    if (!empty($c['signature'])) {
        $firma = '<tr><td style="padding:' . ($cta === '' ? '10px' : '0') . ' 32px 32px;font-family:Arial,Helvetica,sans-serif;">'
            . '<p style="margin:0;font-size:15px;line-height:1.65;color:#393E4A;">' . $c['signature'] . '</p>'
            . '</td></tr>';
    }

    return '<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>' . htmlspecialchars($c['subject'], ENT_QUOTES, 'UTF-8') . '</title>
</head>
<body style="margin:0;padding:0;background:#F4F7F6;">

<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . htmlspecialchars($c['preview'], ENT_QUOTES, 'UTF-8') . '</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#F4F7F6;">
<tr><td align="center" style="padding:32px 16px;">

  <table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:560px;background:#FFFFFF;border-radius:16px;border:1px solid #E6EBE9;">

    <tr>
      <td align="center" style="padding:32px 32px 8px;">
        <img src="' . BALANS_LOGO_URL . '" width="140" height="40" alt="Balans" style="display:block;border:0;height:auto;">
      </td>
    </tr>

    <tr>
      <td style="padding:24px 32px 0;">
        <h1 style="margin:0 0 22px;font-family:Arial,Helvetica,sans-serif;font-size:24px;line-height:1.3;color:#1A1D24;text-align:center;">' . htmlspecialchars($c['heading'], ENT_QUOTES, 'UTF-8') . '</h1>
      </td>
    </tr>

    <tr>
      <td style="padding:0 32px;font-family:Arial,Helvetica,sans-serif;">
        <p style="margin:0 0 16px;font-size:15px;line-height:1.65;color:#393E4A;">' . $c['intro'] . '</p>
        ' . $paragraphs . '
      </td>
    </tr>

    ' . $cta . '

    ' . $firma . '

    <tr>
      <td style="padding:0 32px 32px;">
        <div style="border-top:1px solid #E6EBE9;padding-top:20px;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.6;color:#6B7080;">
          <p style="margin:0 0 8px;">' . htmlspecialchars($c['footer_reason'], ENT_QUOTES, 'UTF-8') . '</p>
          <p style="margin:0;">Se non hai fatto tu questa richiesta, ignora pure questo messaggio.<br>
          <a href="' . BALANS_PRIVACY_URL . '" style="color:#0E7A66;">Privacy Policy</a> &nbsp;&middot;&nbsp;
          <a href="' . BALANS_SITE_URL . '" style="color:#0E7A66;">balansapp.it</a></p>
        </div>
      </td>
    </tr>

  </table>

  <p style="margin:20px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#9CA0AE;">Balans &mdash; fatture e movimenti della tua partita IVA, in pochissimo tempo.</p>

</td></tr>
</table>

</body>
</html>';
}

/** Ripulisce un frammento dai tag HTML per la versione testuale. */
function balans_solo_testo($html)
{
    return trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'));
}

/**
 * Versione testuale. Va sempre allegata: i filtri antispam penalizzano
 * le email con il solo corpo HTML.
 */
function balans_email_plaintext($c)
{
    $lines = array($c['heading'], '', balans_solo_testo(str_replace('<br>', "\n", $c['intro'])), '');

    foreach ($c['paragraphs'] as $p) {
        if (!is_array($p)) {
            $lines[] = balans_solo_testo($p);
            $lines[] = '';
            continue;
        }

        if (!empty($p['testo'])) {
            $lines[] = balans_solo_testo($p['testo']);
        }
        if (isset($p['voci'])) {
            foreach ($p['voci'] as $v) {
                $lines[] = '- ' . balans_solo_testo($v);
            }
        }
        $lines[] = '';
    }

    if (!empty($c['cta_label']) && !empty($c['cta_url'])) {
        $lines[] = $c['cta_label'] . ': ' . $c['cta_url'];
        $lines[] = '';
    }

    if (!empty($c['signature'])) {
        $lines[] = balans_solo_testo(str_replace('<br>', "\n", $c['signature']));
        $lines[] = '';
    }

    $lines[] = '---';
    $lines[] = $c['footer_reason'];
    $lines[] = 'Se non hai fatto tu questa richiesta, ignora pure questo messaggio.';
    $lines[] = 'Privacy Policy: ' . BALANS_PRIVACY_URL;

    return implode("\n", $lines);
}

/* =========================================================================
 * NOTIFICA INTERNA
 *
 * L'email che avvisa il team a ogni nuova iscrizione: prima la mandava
 * Web3Forms, adesso parte da qui. Ha un layout suo, piu' asciutto di quello
 * delle email agli iscritti, perche' serve a leggere dei dati e non a fare
 * bella figura.
 *
 * Riporta anche l'esito della registrazione nello studio: se la chiamata
 * all'API non e' andata a buon fine, questa email resta l'unica traccia del
 * contatto, e deve dirlo chiaramente.
 * ========================================================================= */

/**
 * @param array $dati   array('type','email','name','quando','origine','consenso')
 * @param array $studio esito di balans_studio_create_contact()
 * @return array pronto per balans_internal_layout()
 */
function balans_internal_notification($dati, $studio)
{
    $etichetta = $dati['type'] === 'demo'
        ? 'Richiesta demo — Balans Business'
        : 'Waiting list — Balans';

    if (!empty($studio['ok'])) {
        $stato = 'ok';
        $titoloEsito = 'Registrato in StudioGestione';
        $noteEsito = $studio['id'] !== null
            ? 'Contatto creato con ID ' . $studio['id'] . '.'
            : 'Contatto creato (lo studio non ha restituito un ID).';
    } elseif ($studio['skipped'] !== '') {
        $stato = 'neutro';
        $titoloEsito = 'Non inviato allo studio';
        $noteEsito = 'Motivo: ' . $studio['skipped'] . '. Nessun contatto e\' stato creato.';
    } else {
        $stato = 'ko';
        $titoloEsito = 'NON registrato — da inserire a mano';
        $noteEsito = 'La chiamata all\'API dello studio non e\' riuscita (' . rtrim($studio['error'], '. ')
            . '). I dati qui sotto sono l\'unica traccia di questa iscrizione: vanno inseriti a mano.';
    }

    $righe = array(
        'Tipo di richiesta' => $etichetta,
        'Nome'              => $dati['name'] !== '' ? $dati['name'] : '(non indicato)',
        'Email'             => $dati['email'],
        'Consenso privacy'  => $dati['consenso'],
        'Pagina'            => $dati['origine'] !== '' ? $dati['origine'] : '(non rilevata)',
        'Data e ora'        => $dati['quando'],
    );

    // Quando il nome digitato manca, all'API ne e' stato mandato uno di
    // ripiego: chi legge deve sapere cosa si trovera' in anagrafica.
    if (isset($studio['payload']['name']) && $studio['payload']['name'] !== $dati['name']) {
        $righe['Nome in anagrafica'] = $studio['payload']['name'];
    }

    return array(
        'subject' => ($dati['type'] === 'demo' ? 'Nuova richiesta demo' : 'Nuova iscrizione waiting list')
                     . ' — ' . $dati['email'],
        'stato'       => $stato,
        'titolo'      => $titoloEsito,
        'nota'        => $noteEsito,
        'righe'       => $righe,
    );
}

/** Versione HTML della notifica interna. */
function balans_internal_layout($n)
{
    $colori = array(
        'ok'     => array('#0E7A66', '#EEF8F4', '#BEE6DB'),
        'ko'     => array('#A03028', '#FAECEA', '#EAC4BF'),
        'neutro' => array('#6B7080', '#F4F7F6', '#E6EBE9'),
    );
    list($testo, $sfondo, $bordo) = $colori[$n['stato']];

    $righe = '';
    foreach ($n['righe'] as $etichetta => $valore) {
        $righe .= '<tr>'
            . '<td style="padding:9px 14px 9px 0;font-size:12px;letter-spacing:.05em;text-transform:uppercase;color:#9CA0AE;white-space:nowrap;vertical-align:top;">'
            . htmlspecialchars($etichetta, ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td style="padding:9px 0;font-size:15px;color:#1A1D24;border-bottom:1px solid #F0F3F2;">'
            . htmlspecialchars($valore, ENT_QUOTES, 'UTF-8') . '</td>'
            . '</tr>';
    }

    return '<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>' . htmlspecialchars($n['subject'], ENT_QUOTES, 'UTF-8') . '</title>
</head>
<body style="margin:0;padding:0;background:#F4F7F6;">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#F4F7F6;">
<tr><td align="center" style="padding:28px 16px;">

  <table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:560px;background:#FFFFFF;border-radius:14px;border:1px solid #E6EBE9;font-family:Arial,Helvetica,sans-serif;">

    <tr>
      <td style="padding:26px 28px 0;">
        <p style="margin:0 0 18px;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#9CA0AE;">Balans &middot; notifica automatica</p>
        <div style="background:' . $sfondo . ';border:1px solid ' . $bordo . ';border-radius:10px;padding:14px 16px;">
          <p style="margin:0 0 4px;font-size:15px;font-weight:bold;color:' . $testo . ';">' . htmlspecialchars($n['titolo'], ENT_QUOTES, 'UTF-8') . '</p>
          <p style="margin:0;font-size:13.5px;line-height:1.55;color:#393E4A;">' . htmlspecialchars($n['nota'], ENT_QUOTES, 'UTF-8') . '</p>
        </div>
      </td>
    </tr>

    <tr>
      <td style="padding:22px 28px 6px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">' . $righe . '</table>
      </td>
    </tr>

    <tr>
      <td style="padding:16px 28px 26px;">
        <p style="margin:0;font-size:12px;line-height:1.6;color:#6B7080;">Puoi rispondere direttamente a questa email: la risposta arriva all\'indirizzo dell\'iscritto.</p>
      </td>
    </tr>

  </table>

</td></tr>
</table>

</body>
</html>';
}

/** Versione testuale della notifica interna. */
function balans_internal_plaintext($n)
{
    $lines = array($n['titolo'], $n['nota'], '');

    foreach ($n['righe'] as $etichetta => $valore) {
        $lines[] = $etichetta . ': ' . $valore;
    }

    $lines[] = '';
    $lines[] = '---';
    $lines[] = 'Notifica automatica del sito balansapp.it.';
    $lines[] = 'Rispondendo a questa email si scrive direttamente all\'iscritto.';

    return implode("\n", $lines);
}
