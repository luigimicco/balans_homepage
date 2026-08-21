// Theme toggle (light/dark)
(function () {
    const KEY = 'balans-theme';
    const saved = localStorage.getItem(KEY);
    if (saved === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.theme-toggle');
        if (!btn) return;
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        if (isDark) {
            document.documentElement.removeAttribute('data-theme');
            localStorage.setItem(KEY, 'light');
        } else {
            document.documentElement.setAttribute('data-theme', 'dark');
            localStorage.setItem(KEY, 'dark');
        }
    });
})();

// FAQ accordion
document.querySelectorAll('.faq-q').forEach(q => {
    q.addEventListener('click', () => {
        const item = q.closest('.faq-item');
        const wasOpen = item.classList.contains('open');
        document.querySelectorAll('.faq-item').forEach(i => i.classList.remove('open'));
        if (!wasOpen) item.classList.add('open');
    });
});

// Demo interattiva — flusso guidato automatico (CTA -> Invia -> typing -> risposta -> ripeti)
(function () {
    const section = document.querySelector('.hero');
    const phone = document.querySelector('.hero .phone');
    if (!section || !phone) return;

    const overlay = phone.querySelector('.demo-cta-overlay');
    const ctaBtn = phone.querySelector('.demo-cta-btn');
    const welcome = phone.querySelector('.demo-welcome');
    const chat = phone.querySelector('.demo-chat');
    const qSlot = phone.querySelector('.demo-q-slot');
    const sendBtn = phone.querySelector('.send-btn');
    const REDUCED = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const PLACEHOLDER = 'Scrivi a Balans...';

    const ANSWERS = {
        c2q1: '<div class="bub ai-card" style="max-width:88%">'
            + '<p class="msg">Riepilogo fattura n.21/2026 a regime forfettario (P.IVA 01234567890) per "Consulenza"<br>500€ + IVA 0% (€0,00) = <b>€500 totali</b><br>Scadenza pagamento <b>2026-08-15</b>. Confermo l\'emissione?</p>'
            + '<button class="demo-invia" type="button">Conferma fattura</button>'
            + '</div>'
            + '<div class="bub ai">Ho preparato la fattura per Studio Bianchi di €500,00 con causale «Consulenza». Clicca su <b>Conferma</b> per inoltrarla.</div>',
        qBestClient: '<div class="bub ai">Il tuo miglior cliente è <b>Verdi Consulting Srl</b>: quest\'anno ha generato un fatturato totale di <b>€12.300</b>, il 27% del tuo fatturato complessivo.</div>',
        qMonthRevenue: '<div class="bub ai">Questo mese hai fatturato <b>€4.230</b> con 7 fatture. Sei al 22% del tuo limite forfettario annuale.</div>'
    };

    function scrollBottom() {
        // rAF: su iOS Safari leggere/impostare scrollTop nello stesso tick
        // dell'inserimento DOM può usare un layout non ancora committato,
        // lasciando la bolla appena aggiunta fuori vista finché non arriva
        // un altro repaint
        requestAnimationFrame(() => {
            chat.scrollTop = chat.scrollHeight;
        });
    }

    function appendHTML(html) {
        const wrap = document.createElement('div');
        wrap.innerHTML = html.trim();
        const nodes = Array.from(wrap.children);
        nodes.forEach(n => chat.appendChild(n));
        return nodes;
    }

    // Fa comparire una bolla con la stessa animazione "pop" usata dalla chat
    // statica in animations.js (richiede un frame già dipinto con opacity:0
    // prima di aggiungere .pop, altrimenti il browser salta la transizione)
    function popIn(el, delay) {
        if (REDUCED) { el.classList.add('pop'); return; }
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                setTimeout(() => el.classList.add('pop'), delay || 0);
            });
        });
    }

    // Sequenza fissa delle domande mostrate nella barra di testo del telefono
    const QUESTIONS = [
        ['c2q1', 'Crea una fattura di 500€ per la prestazione di ieri a Studio Bianchi.'],
        ['qBestClient', 'Chi è il mio miglior cliente?'],
        ['qMonthRevenue', 'Quanto ho fatturato questo mese?']
    ];
    const FLOW = QUESTIONS.map(([key, text]) => ({ key, text, answer: ANSWERS[key] }));

    let idx = 0;

    function setSendEnabled(enabled) {
        sendBtn.disabled = !enabled;
        sendBtn.classList.toggle('pulse', enabled);
    }

    // Scrive il testo carattere per carattere nello slot della domanda,
    // simulando l'utente che digita (lo slot resta comunque sempre di sola lettura)
    function typeInto(el, text, onDone) {
        el.classList.add('filled', 'typing');
        if (REDUCED) {
            el.textContent = text;
            el.classList.remove('typing');
            onDone();
            return;
        }
        el.textContent = '';
        let i = 0;
        const timer = setInterval(() => {
            el.textContent = text.slice(0, ++i);
            if (i >= text.length) {
                clearInterval(timer);
                el.classList.remove('typing');
                onDone();
            }
        }, 26);
    }

    function activateDemo() {
        section.setAttribute('data-mode', 'demo');
        overlay.classList.add('hidden');
        idx = 0;
        qSlot.textContent = FLOW[0].text;
        qSlot.classList.add('filled');
        setSendEnabled(true);
    }

    function sendCurrent() {
        if (sendBtn.disabled) return;
        setSendEnabled(false);
        welcome.classList.add('hidden');
        chat.classList.add('active');

        const bubble = document.createElement('div');
        bubble.className = 'bub me';
        bubble.textContent = FLOW[idx].text;
        chat.appendChild(bubble);
        popIn(bubble);
        qSlot.textContent = PLACEHOLDER;
        qSlot.classList.remove('filled');

        const [typing] = appendHTML('<div class="typing-bub"><span></span><span></span><span></span></div>');
        scrollBottom();

        setTimeout(() => {
            typing.remove();
            const nodes = appendHTML(FLOW[idx].answer || '<div class="bub ai">…</div>');
            nodes.forEach((n, i) => popIn(n, i * 90));
            scrollBottom();
            setTimeout(advance, REDUCED ? 0 : 500);
        }, REDUCED ? 0 : 2000);
    }

    function advance() {
        idx++;
        if (idx < FLOW.length) {
            typeInto(qSlot, FLOW[idx].text, () => setSendEnabled(true));
        } else {
            finishFlow();
        }
    }

    function finishFlow() {
        qSlot.textContent = PLACEHOLDER;
        qSlot.classList.remove('filled');

        setTimeout(() => {
            const nodes = appendHTML('<div class="chat-sys-note">Questa era solo una simulazione: Balans può fare molto di più per te, ogni giorno</div>');
            nodes.forEach(n => popIn(n));
            scrollBottom();
        }, REDUCED ? 0 : 2000);
    }

    // La "Invia fattura" dentro la card di risposta è generata dinamicamente,
    // quindi il click va delegato sul contenitore della chat
    chat.addEventListener('click', (e) => {
        const invia = e.target.closest('.demo-invia');
        if (!invia || invia.disabled) return;
        invia.disabled = true;

        // Il secondo bottone "Invia fattura" (dopo la conferma) si disabilita
        // e basta, senza cambiare testo né mostrare altri messaggi
        if (invia.classList.contains('demo-invia-final')) return;

        invia.innerHTML = '<img src="./assets/img/icons/vettoriale/check.svg" alt="" style="width:14px; height:14px; display:inline-block; flex-shrink:0; filter:brightness(0) invert(1);">Confermata';

        const [typing] = appendHTML('<div class="typing-bub"><span></span><span></span><span></span></div>');
        scrollBottom();

        setTimeout(() => {
            typing.remove();
            const nodes = appendHTML(
                '<div class="bub ai-card" style="max-width:88%">'
                + '<p class="msg">Perfetto, hai completato la fattura. Cosa vuoi fare adesso?</p>'
                + '<button class="demo-invia demo-invia-final" type="button">Invia fattura</button>'
                + '</div>'
            );
            nodes.forEach((n, i) => popIn(n, i * 90));
            scrollBottom();
        }, REDUCED ? 0 : 1200);
    });

    ctaBtn.addEventListener('click', activateDemo);
    sendBtn.addEventListener('click', sendCurrent);
})();

// Waitlist / demo forms — submit a /api/subscribe.php, confirmation modal, satellite buttons scroll to form
//
// L'invio è in due passi: al primo click si valida la sola email, che lascia il
// posto al campo nome; al secondo si invia tutto. Finché resta nascosto il campo
// nome è anche `disabled`, così non entra né in FormData né in checkValidity().
// L'email invece viene solo nascosta: disabilitarla la toglierebbe dai dati.
function initWaitlistForm({ formId, modalId, emailId, nameId, errorId, scrollAttr, confirmationType, step2Label }) {
    const form = document.getElementById(formId);
    const modal = document.getElementById(modalId);
    if (!form || !modal) return;

    const emailInput = document.getElementById(emailId);
    const nameInput = document.getElementById(nameId);
    const errorBox = document.getElementById(errorId);
    const submitBtn = form.querySelector('.hero-btn');
    const submitLabel = submitBtn.querySelector('.btn-label') || submitBtn;
    const submitLabelInitial = submitLabel.textContent;
    // Etichetta da ripristinare dopo un invio: cambia quando si passa al passo 2.
    let submitLabelDefault = submitLabelInitial;
    let nameRevealed = false;

    // La classe sul form pilota l'incrocio dei due campi; qui restano solo gli
    // attributi che decidono cosa viene inviato e cosa viene validato.
    function revealName() {
        nameRevealed = true;
        nameInput.disabled = false;
        nameInput.required = true;
        form.classList.add('is-step2');
        submitLabelDefault = step2Label;
        submitLabel.textContent = step2Label;
        // Il focus dopo un frame: il campo è ancora `visibility: hidden` finché
        // il browser non ha applicato la classe, e un campo invisibile non si
        // può mettere a fuoco.
        requestAnimationFrame(() => nameInput.focus({ preventScroll: true }));
    }

    function collapseName() {
        if (!nameRevealed) return;
        nameRevealed = false;
        // form.reset() svuota i campi ma non tocca disabled/required, che
        // abbiamo impostato via JS: vanno riportati a mano.
        nameInput.required = false;
        nameInput.disabled = true;
        form.classList.remove('is-step2');
        submitLabelDefault = submitLabelInitial;
        submitLabel.textContent = submitLabelInitial;
    }

    function showError(msg) {
        if (!errorBox) return;
        errorBox.textContent = msg;
        errorBox.hidden = false;
    }

    function clearError() {
        if (!errorBox) return;
        errorBox.hidden = true;
        errorBox.textContent = '';
    }

    function openModal() {
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        const ok = modal.querySelector('.wl-modal-ok');
        if (ok) ok.focus();
    }

    function closeModal() {
        modal.hidden = true;
        document.body.style.overflow = '';
    }

    modal.addEventListener('click', (e) => {
        if (e.target === modal || e.target.closest('.wl-modal-close') || e.target.closest('.wl-modal-ok')) {
            closeModal();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.hidden) closeModal();
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearError();

        // Passo 1: si controlla la sola email, così la checkbox privacy non
        // viene contestata prima che l'utente abbia finito di compilare.
        if (nameInput && !nameRevealed) {
            if (!emailInput.checkValidity()) {
                emailInput.reportValidity();
                return;
            }
            revealName();
            return;
        }

        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        submitBtn.disabled = true;
        submitLabel.textContent = 'Invio in corso...';

        // L'endpoint distingue waiting list e demo dal campo `type`: non è nel
        // form perché lo sa già il codice che lo inizializza.
        const payload = Object.fromEntries(new FormData(form));
        payload.type = confirmationType;

        try {
            const res = await fetch(form.action, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.success) {
                form.reset();
                collapseName();
                openModal();
            } else {
                showError('Non siamo riusciti a completare l\'iscrizione. Riprova tra poco.');
            }
        } catch (err) {
            showError('Errore di connessione. Controlla la rete e riprova.');
        } finally {
            submitBtn.disabled = false;
            submitLabel.textContent = submitLabelDefault;
        }
    });

    // Satellite buttons: scroll to the form and focus the field still to fill.
    // Se il form è già al passo 2 puntiamo al nome invece che all'email, così
    // non si azzera quello che l'utente ha già scritto.
    document.querySelectorAll(`[${scrollAttr}]`).forEach((btn) => {
        btn.addEventListener('click', () => {
            const target = nameRevealed ? nameInput : emailInput;
            form.scrollIntoView({ behavior: 'smooth', block: 'center' });
            target.classList.remove('wl-highlight');
            void target.offsetWidth; // restart animation
            target.classList.add('wl-highlight');
            window.setTimeout(() => target.focus({ preventScroll: true }), 400);
        });
    });
}

initWaitlistForm({ formId: 'waitlist-form', modalId: 'waitlist-modal', emailId: 'waitlist-email', nameId: 'waitlist-name', errorId: 'waitlist-error', scrollAttr: 'data-waitlist-scroll', confirmationType: 'waitlist', step2Label: 'Conferma iscrizione' });

initWaitlistForm({ formId: 'demo-form', modalId: 'demo-modal', emailId: 'demo-email', nameId: 'demo-name', errorId: 'demo-error', scrollAttr: 'data-demo-scroll', confirmationType: 'demo', step2Label: 'Invia richiesta' });
