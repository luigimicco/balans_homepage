/* ══════════════════════════════════════════════════════════════════
   COOKIE CONSENT — Balans (vanilla JS, nessuna dipendenza)

   Gestisce: banner di informativa breve, modale preferenze, memorizza-
   zione del consenso in un cookie tecnico di prima parte e sblocco
   degli script/contenuti di terze parti soggetti a consenso.

   ── Come bloccare preventivamente uno script di terza parte ──
   Nel markup, lo script va inserito con type="text/plain" (il browser
   non lo esegue) e la categoria di consenso in data-cc:

     <script type="text/plain" data-cc="profiling" data-src="https://..."></script>
     <script type="text/plain" data-cc="profiling">codice inline</script>

   ── Come bloccare preventivamente un iframe (es. video YouTube) ──
   L'iframe va inserito senza src reale, usando data-src:

     <iframe data-cc="profiling" data-src="https://www.youtube-nocookie.com/embed/ID"></iframe>

   ── Pulsante "click-to-load" su un placeholder ──
   Un elemento con data-cc-allow="profiling" concede il consenso alla
   sola categoria indicata (e sblocca i relativi contenuti):

     <button data-cc-allow="profiling">Accetta e guarda il video</button>

   ── Riapertura del centro preferenze ──
   Qualsiasi elemento con attributo data-cc-open apre la modale.

   ── API pubblica ──
   window.balansCC.open()        → apre la modale preferenze
   window.balansCC.getConsent()  → { v, ts, prefs } oppure null
   Evento "cc:change" su document → emesso ad ogni modifica del consenso.
   ══════════════════════════════════════════════════════════════════ */

(function () {
    'use strict';

    /* ───────── Configurazione ───────── */

    var COOKIE_NAME = 'balans_cookie_consent';

    // Versione del consenso: incrementare quando cambiano le categorie
    // o le finalità → il banner verrà riproposto a tutti gli utenti.
    var CONSENT_VERSION = 1;

    // Durate previste dalla Cookie Policy:
    // 6 mesi per rifiuto/chiusura, 12 mesi per accettazione/preferenze.
    var GIORNI_RIFIUTO = 182;
    var GIORNI_ACCETTAZIONE = 365;

    // Categorie soggette a consenso (i tecnici sono sempre attivi).
    var CATEGORIE = [
        {
            id: 'profiling',
            titolo: 'Cookie di profilazione e contenuti di terze parti',
            descrizione: 'Consentono il caricamento di contenuti incorporati forniti da terze parti ' +
                '(ad esempio video), che possono installare cookie di profilazione. ' +
                'Attualmente il Sito non incorpora tali contenuti: la preferenza verrà ' +
                'applicata a eventuali contenuti futuri.'
        }
    ];

    var POLICY_URL = '/cookie/';

    /* ───────── Lettura / scrittura del cookie di consenso ───────── */

    // Legge e decodifica il cookie di consenso; null se assente o corrotto.
    function leggiConsenso() {
        var match = document.cookie.match(new RegExp('(?:^|;\\s*)' + COOKIE_NAME + '=([^;]*)'));
        if (!match) return null;
        try {
            var dati = JSON.parse(decodeURIComponent(match[1]));
            return (dati && typeof dati === 'object' && dati.prefs) ? dati : null;
        } catch (e) {
            return null;
        }
    }

    // Scrive il cookie di consenso (prima parte, Path=/, SameSite=Lax).
    function scriviConsenso(prefs, giorni) {
        var valore = encodeURIComponent(JSON.stringify({
            v: CONSENT_VERSION,
            ts: new Date().toISOString(),
            prefs: prefs
        }));
        document.cookie = COOKIE_NAME + '=' + valore +
            '; Path=/; Max-Age=' + (giorni * 86400) +
            '; SameSite=Lax' +
            (location.protocol === 'https:' ? '; Secure' : '');
    }

    /* ───────── Sblocco degli script e dei contenuti bloccati ───────── */

    // Attiva tutti gli script e gli iframe della categoria consentita.
    function attivaCategoria(categoria) {
        // Script bloccati: ricreati con type corretto così il browser li esegue.
        document.querySelectorAll('script[type="text/plain"][data-cc="' + categoria + '"]').forEach(function (vecchio) {
            var nuovo = document.createElement('script');
            // Copia tutti gli attributi tranne quelli di blocco.
            Array.prototype.forEach.call(vecchio.attributes, function (attr) {
                if (attr.name === 'type' || attr.name === 'data-src' || attr.name === 'data-cc') return;
                nuovo.setAttribute(attr.name, attr.value);
            });
            nuovo.type = 'text/javascript';
            if (vecchio.dataset.src) {
                nuovo.src = vecchio.dataset.src;
            } else {
                nuovo.textContent = vecchio.textContent;
            }
            vecchio.parentNode.replaceChild(nuovo, vecchio);
        });

        // Iframe bloccati: il vero indirizzo è in data-src.
        document.querySelectorAll('iframe[data-cc="' + categoria + '"][data-src]').forEach(function (frame) {
            frame.src = frame.dataset.src;
            frame.removeAttribute('data-src');
        });
    }

    // Applica le preferenze correnti e notifica gli eventuali listener.
    function applicaConsenso(prefs) {
        CATEGORIE.forEach(function (cat) {
            if (prefs[cat.id]) attivaCategoria(cat.id);
        });
        document.dispatchEvent(new CustomEvent('cc:change', { detail: leggiConsenso() }));
    }

    /* ───────── Costruzione del markup (iniettato via JS) ───────── */

    var banner = null;
    var overlay = null;
    var elementoConFocus = null; // per ripristinare il focus alla chiusura della modale

    function creaBanner() {
        banner = document.createElement('div');
        banner.className = 'cc-banner';
        banner.setAttribute('role', 'region');
        banner.setAttribute('aria-label', 'Informativa cookie');
        banner.innerHTML =
            '<button type="button" class="cc-close" data-cc-action="reject" aria-label="Chiudi il banner senza prestare il consenso">' +
            '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"></path></svg>' +
            '</button>' +
            '<h2>Questo sito utilizza cookie</h2>' +
            '<p class="cc-text">Il nostro Sito utilizza <b>cookie tecnici</b> necessari per il funzionamento del Sito e per consentirti di usufruire correttamente dei servizi nel corso della navigazione, nonché, previo tuo consenso, <b>cookie di profilazione</b> di terza parte qualora vengano visionati contenuti incorporati di terze parti (es. video) presenti sul Sito. Per ulteriori informazioni consulta la nostra <a href="' + POLICY_URL + '">Cookie Policy</a>.<br>' +
            'Per mantenere le impostazioni di default (solo cookie tecnici) senza prestare il consenso, puoi chiudere il banner tramite il comando X posto in alto a destra o cliccare sul tasto &laquo;Rifiuta tutti&raquo;. Per acconsentire all&rsquo;utilizzo di tutti i cookie clicca su &laquo;Accetta tutti&raquo;, oppure clicca su &laquo;Gestisci preferenze&raquo; per maggiori informazioni e per prestare o meno il consenso ai cookie di profilazione.</p>' +
            '<div class="cc-actions">' +
            '<button type="button" class="cc-btn cc-btn-ghost" data-cc-action="prefs">Gestisci preferenze</button>' +
            '<button type="button" class="cc-btn cc-btn-solid" data-cc-action="reject">Rifiuta tutti</button>' +
            '<button type="button" class="cc-btn cc-btn-solid" data-cc-action="accept">Accetta tutti</button>' +
            '</div>';
        document.body.appendChild(banner);
    }

    function rimuoviBanner() {
        if (banner) {
            banner.remove();
            banner = null;
        }
    }

    function creaModale() {
        if (overlay) return;
        overlay = document.createElement('div');
        overlay.className = 'cc-overlay';
        overlay.hidden = true;

        var categorieHtml = CATEGORIE.map(function (cat) {
            return '<div class="cc-cat">' +
                '<div class="cc-cat-head">' +
                '<span class="cc-cat-title" id="cc-title-' + cat.id + '">' + cat.titolo + '</span>' +
                '<label class="cc-switch">' +
                '<input type="checkbox" data-cc-cat="' + cat.id + '" aria-labelledby="cc-title-' + cat.id + '">' +
                '<span class="cc-slider"></span>' +
                '</label>' +
                '</div>' +
                '<p class="cc-cat-desc">' + cat.descrizione + '</p>' +
                '</div>';
        }).join('');

        overlay.innerHTML =
            '<div class="cc-modal" role="dialog" aria-modal="true" aria-labelledby="cc-modal-title">' +
            '<button type="button" class="cc-close" data-cc-action="close" aria-label="Chiudi le preferenze senza salvare">' +
            '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"></path></svg>' +
            '</button>' +
            '<h2 id="cc-modal-title">Preferenze cookie</h2>' +
            '<p class="cc-modal-intro">Qui puoi scegliere quali categorie di cookie autorizzare. I cookie tecnici non possono essere disattivati perch&eacute; necessari al funzionamento del Sito. Per maggiori dettagli consulta la <a href="' + POLICY_URL + '">Cookie Policy</a>.</p>' +
            // Categoria tecnici: sempre attiva, interruttore disabilitato.
            '<div class="cc-cat">' +
            '<div class="cc-cat-head">' +
            '<span class="cc-cat-title" id="cc-title-necessary">Cookie tecnici necessari</span>' +
            '<span class="cc-cat-state">Sempre attivi</span>' +
            '</div>' +
            '<p class="cc-cat-desc">Necessari per il funzionamento del Sito: memorizzano le tue scelte sui cookie e le preferenze di visualizzazione (es. tema chiaro/scuro). Non richiedono consenso.</p>' +
            '</div>' +
            categorieHtml +
            '<div class="cc-actions">' +
            '<button type="button" class="cc-btn cc-btn-ghost" data-cc-action="accept">Accetta tutti</button>' +
            '<button type="button" class="cc-btn cc-btn-solid" data-cc-action="save">Salva preferenze</button>' +
            '</div>' +
            '</div>';
        document.body.appendChild(overlay);

        // Chiusura cliccando fuori dalla modale.
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) chiudiModale();
        });
    }

    /* ───────── Apertura / chiusura modale (con gestione focus) ───────── */

    function apriModale() {
        creaModale();
        // Precompila gli interruttori con le scelte correnti (default: off).
        var salvato = leggiConsenso();
        overlay.querySelectorAll('input[data-cc-cat]').forEach(function (input) {
            input.checked = !!(salvato && salvato.prefs[input.dataset.ccCat]);
        });
        elementoConFocus = document.activeElement;
        overlay.hidden = false;
        document.documentElement.style.overflow = 'hidden'; // blocca lo scroll di sfondo
        // Porta il focus sul primo elemento interattivo della modale.
        var primo = overlay.querySelector('.cc-btn, input, .cc-close');
        if (primo) primo.focus();
        document.addEventListener('keydown', gestioneTastiera);
    }

    function chiudiModale() {
        if (!overlay || overlay.hidden) return;
        overlay.hidden = true;
        document.documentElement.style.overflow = '';
        document.removeEventListener('keydown', gestioneTastiera);
        if (elementoConFocus && elementoConFocus.focus) elementoConFocus.focus();
        // Se l'utente non ha ancora espresso una scelta, il banner deve restare visibile.
        if (!leggiConsenso() && !banner) creaBanner();
    }

    // Esc chiude la modale; Tab resta intrappolato al suo interno.
    function gestioneTastiera(e) {
        if (e.key === 'Escape') {
            chiudiModale();
            return;
        }
        if (e.key !== 'Tab') return;
        var focusabili = overlay.querySelectorAll('button, input, a[href]');
        if (!focusabili.length) return;
        var primo = focusabili[0];
        var ultimo = focusabili[focusabili.length - 1];
        if (e.shiftKey && document.activeElement === primo) {
            e.preventDefault();
            ultimo.focus();
        } else if (!e.shiftKey && document.activeElement === ultimo) {
            e.preventDefault();
            primo.focus();
        }
    }

    /* ───────── Azioni di consenso ───────── */

    function tuttePreferenze(valore) {
        var prefs = {};
        CATEGORIE.forEach(function (cat) { prefs[cat.id] = valore; });
        return prefs;
    }

    // Verifica se una categoria prima consentita è stata revocata:
    // in tal caso serve ricaricare la pagina per rimuovere i contenuti
    // di terze parti già caricati (unico metodo affidabile).
    function serveRicaricare(vecchio, nuovo) {
        if (!vecchio) return false;
        return CATEGORIE.some(function (cat) {
            return vecchio.prefs[cat.id] === true && nuovo[cat.id] === false;
        });
    }

    function salvaEApplica(prefs, giorni) {
        var precedente = leggiConsenso();
        scriviConsenso(prefs, giorni);
        rimuoviBanner();
        chiudiModale();
        if (serveRicaricare(precedente, prefs)) {
            location.reload();
            return;
        }
        applicaConsenso(prefs);
    }

    function accettaTutti() {
        salvaEApplica(tuttePreferenze(true), GIORNI_ACCETTAZIONE);
    }

    function rifiutaTutti() {
        salvaEApplica(tuttePreferenze(false), GIORNI_RIFIUTO);
    }

    function salvaPreferenze() {
        var prefs = {};
        overlay.querySelectorAll('input[data-cc-cat]').forEach(function (input) {
            prefs[input.dataset.ccCat] = input.checked;
        });
        salvaEApplica(prefs, GIORNI_ACCETTAZIONE);
    }

    // Concede il consenso a una singola categoria (pulsanti click-to-load).
    function concedeCategoria(categoria) {
        var salvato = leggiConsenso();
        var prefs = salvato ? salvato.prefs : tuttePreferenze(false);
        prefs[categoria] = true;
        salvaEApplica(prefs, GIORNI_ACCETTAZIONE);
    }

    /* ───────── Delega degli eventi ───────── */

    document.addEventListener('click', function (e) {
        // Pulsanti del banner e della modale.
        var azione = e.target.closest('[data-cc-action]');
        if (azione) {
            switch (azione.dataset.ccAction) {
                case 'accept': accettaTutti(); break;
                case 'reject': rifiutaTutti(); break;
                case 'save': salvaPreferenze(); break;
                case 'prefs': apriModale(); break;
                case 'close': chiudiModale(); break;
            }
            return;
        }
        // Link/pulsante "Preferenze cookie" (footer, cookie policy, ecc.).
        var apri = e.target.closest('[data-cc-open]');
        if (apri) {
            e.preventDefault();
            apriModale();
            return;
        }
        // Pulsanti click-to-load nei placeholder dei contenuti bloccati.
        var consenti = e.target.closest('[data-cc-allow]');
        if (consenti) {
            e.preventDefault();
            concedeCategoria(consenti.dataset.ccAllow);
        }
    });

    /* ───────── Inizializzazione ───────── */

    function init() {
        var salvato = leggiConsenso();
        if (!salvato || salvato.v !== CONSENT_VERSION) {
            // Nessuna scelta valida memorizzata → mostra il banner.
            creaBanner();
        } else {
            // Scelta già espressa → applica senza mostrare nulla.
            applicaConsenso(salvato.prefs);
        }
    }

    // Lo script è caricato con "defer": il DOM è già disponibile,
    // ma gestiamo comunque il caso di inclusione nell'head.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    /* ───────── API pubblica ───────── */

    window.balansCC = {
        open: apriModale,
        getConsent: leggiConsenso
    };

})();
