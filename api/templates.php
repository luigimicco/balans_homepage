<?php
/**
 * Testi e layout delle email di conferma.
 *
 * Per cambiare il contenuto di una mail modifica solo l'array in
 * balans_email_content(): oggetto, titolo, paragrafi, testo del bottone.
 * Il layout HTML (balans_email_layout) di norma non va toccato.
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
 * @return array|null  null se il tipo non esiste
 */
function balans_email_content($type)
{
    $contents = array(

        'waitlist' => array(
            'subject'  => 'Confermata l\'iscrizione alla waiting list',
            'preview'  => 'Sei tra i primi che potranno provare Balans. Ecco cosa succede adesso.',
            'heading'  => 'Sei in lista!',
            'intro'    => 'Sei in lista: sarai tra i primi a mettere le mani su Balans.',
            'paragraphs' => array(
                'Balans nasce per una cosa sola: smettere di perdere le serate dietro a fatture e movimenti. Scrivi quello che ti serve, come faresti in chat, e il resto lo fa l\'app.',
                'Apriamo gli accessi a ondate e chi è in lista entra per primo, con condizioni riservate. Ti scriviamo a questo indirizzo appena tocca a te.',
            ),
            'cta_label' => 'Guarda cosa sa fare',
            'cta_url'   => BALANS_SITE_URL,
            'footer_reason' => 'Ricevi questa email perché hai richiesto di entrare nella waiting list su balansapp.it.',
        ),

        'demo' => array(
            'subject'  => 'La tua richiesta di demo è arrivata',
            'preview'  => 'Ti ricontattiamo per organizzare insieme la demo di Balans Business.',
            'heading'  => 'Ti ricontattiamo a breve',
            'intro'    => 'Abbiamo ricevuto la tua richiesta di demo per Balans Business.',
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
 * Versione HTML dell'email: tabelle e stili inline, gli unici che i client
 * di posta (Outlook e Gmail in testa) rendono in modo affidabile.
 */
function balans_email_layout($c)
{
    $paragraphs = '';
    foreach ($c['paragraphs'] as $p) {
        $paragraphs .= '<p style="margin:0 0 16px;font-size:15px;line-height:1.65;color:#393E4A;">' . $p . '</p>';
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
        <p style="margin:0 0 16px;font-size:16px;line-height:1.6;color:#1A1D24;">' . htmlspecialchars($c['intro'], ENT_QUOTES, 'UTF-8') . '</p>
        ' . $paragraphs . '
      </td>
    </tr>

    <tr>
      <td align="center" style="padding:12px 32px 32px;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
          <tr><td align="center" style="background:#1ABFA0;border-radius:999px;">
            <a href="' . $c['cta_url'] . '" style="display:inline-block;padding:14px 30px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:bold;color:#FFFFFF;text-decoration:none;">' . htmlspecialchars($c['cta_label'], ENT_QUOTES, 'UTF-8') . '</a>
          </td></tr>
        </table>
      </td>
    </tr>

    <tr>
      <td style="padding:0 32px 32px;">
        <div style="border-top:1px solid #E6EBE9;padding-top:20px;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.6;color:#6B7080;">
          <p style="margin:0 0 8px;">' . htmlspecialchars($c['footer_reason'], ENT_QUOTES, 'UTF-8') . '</p>
          <p style="margin:0;">Se non sei stato tu, ignora pure questo messaggio.<br>
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

/**
 * Versione testuale. Va sempre allegata: i filtri antispam penalizzano
 * le email con il solo corpo HTML.
 */
function balans_email_plaintext($c)
{
    $lines = array($c['heading'], '', $c['intro'], '');

    foreach ($c['paragraphs'] as $p) {
        $lines[] = trim(strip_tags(str_replace('&nbsp;', ' ', $p)));
        $lines[] = '';
    }

    $lines[] = $c['cta_label'] . ': ' . $c['cta_url'];
    $lines[] = '';
    $lines[] = '---';
    $lines[] = $c['footer_reason'];
    $lines[] = 'Se non sei stato tu, ignora pure questo messaggio.';
    $lines[] = 'Privacy Policy: ' . BALANS_PRIVACY_URL;

    return implode("\n", $lines);
}
