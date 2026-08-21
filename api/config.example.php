<?php
/**
 * Configurazione dell'email di conferma inviata all'utente.
 *
 * COME SI USA
 *  1. Duplica questo file nella stessa cartella e chiamalo "config.php".
 *  2. Compila i valori qui sotto con i dati reali della casella OVH.
 *  3. Carica "config.php" sul server via FTP.
 *
 * config.php non viene versionato su git perché contiene la password.
 */

return array(

    // --- SMTP OVH -------------------------------------------------------
    // Sono i parametri che trovi nel Manager OVH, sezione "Email".
    'smtp_host'   => 'ssl0.ovh.net',
    'smtp_port'   => 587,
    'smtp_secure' => 'tls',                     // 'tls' con la porta 587, 'ssl' con la 465
    'smtp_user'   => 'info@balansapp.it',       // indirizzo completo della casella
    'smtp_pass'   => 'INSERISCI-LA-PASSWORD',

    // --- Mittente -------------------------------------------------------
    // from_email deve essere lo stesso indirizzo di smtp_user, altrimenti
    // OVH rifiuta l'invio.
    'from_email'  => 'info@balansapp.it',
    'from_name'   => 'Balans',

    // Serve solo se un giorno il mittente diventasse un indirizzo diverso
    // da quello a cui vuoi ricevere le risposte (es. un no-reply).
    // Lasciato vuoto, le risposte tornano su from_email.
    'reply_to'    => '',

    // --- StudioGestione (API esterna del team) ---------------------------
    // Le iscrizioni vengono registrate in anagrafica tramite l'API dello
    // studio. La chiave si genera dalla scheda studio, tab "API esterne":
    // viene mostrata in chiaro una sola volta, quindi va copiata subito.
    'studio_api_base'    => 'https://studio.luigimicco.it/api/v1',
    'studio_api_key'     => 'INSERISCI-LA-CHIAVE-API',   // formato sk_live_...
    'studio_api_enabled' => true,

    // Con dry_run attivo il payload viene costruito e registrato nel log,
    // ma NON viene inviato: serve a provare tutto il resto senza creare
    // contatti veri nello studio. Va messo a false solo alla fine.
    'studio_api_dry_run' => true,

    // Secondi di attesa massima per la chiamata. Tenuto basso di proposito:
    // se lo studio non risponde, l'iscrizione non deve restare appesa.
    'studio_api_timeout' => 6,

    // --- Notifica interna -----------------------------------------------
    // Indirizzo che riceve l'avviso a ogni nuova iscrizione. Prima lo faceva
    // Web3Forms; adesso l'email parte da qui e riporta anche l'esito della
    // registrazione nello studio.
    'notify_email'       => 'info@balansapp.it',

    // --- Sicurezza ------------------------------------------------------
    // Solo le pagine servite da questi domini possono chiamare l'endpoint.
    'allowed_origins' => array(
        'https://www.balansapp.it',
        'https://balansapp.it',
    ),

    // Password per aprire /api/diagnostica.php dal browser.
    // Mettici una stringa lunga e casuale.
    'diagnostics_token' => 'CAMBIA-QUESTA-STRINGA',

    // --- Limiti anti-abuso ----------------------------------------------
    // Tenuto largo: le reti mobili fanno uscire tanti utenti dallo stesso IP.
    'max_per_ip_hour'    => 12,    // invii massimi da uno stesso IP in un'ora
    'dedupe_hours'       => 24,    // non riscrive alla stessa email prima di N ore
);
