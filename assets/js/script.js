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
            + '<div class="h"><img src="./assets/img/icons/vettoriale/doc.svg" alt="" style="width:11px; height:11px; display:inline-block; flex-shrink:0; filter:brightness(0) saturate(100%) invert(40%) sepia(30%) saturate(1000%) hue-rotate(120deg) brightness(90%);"> Fattura pronta</div>'
            + '<div class="row"><span class="k">Cliente</span><span class="v">Studio Bianchi</span></div>'
            + '<div class="row"><span class="k">Causale</span><span class="v">Consulenza</span></div>'
            + '<div class="row"><span class="k">Importo</span><span class="v">€ 500,00</span></div>'
            + '<button class="demo-invia" type="button">Invia fattura</button>'
            + '</div>'
            + '<div class="bub ai">Ho preparato la fattura per Studio Bianchi di €500,00 con causale «Consulenza». Clicca su <b>Invia</b> per inoltrarla.</div>',
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
        scrollBottom();
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
        invia.innerHTML = '<img src="./assets/img/icons/vettoriale/check.svg" alt="" style="width:14px; height:14px; display:inline-block; flex-shrink:0; filter:brightness(0) invert(1);">Inviato';
    });

    ctaBtn.addEventListener('click', activateDemo);
    sendBtn.addEventListener('click', sendCurrent);
})();

// Waitlist / demo forms — submit via Web3Forms, confirmation modal, satellite buttons scroll to form
function initWaitlistForm({ formId, modalId, emailId, errorId, scrollAttr }) {
    const form = document.getElementById(formId);
    const modal = document.getElementById(modalId);
    if (!form || !modal) return;

    const emailInput = document.getElementById(emailId);
    const errorBox = document.getElementById(errorId);
    const submitBtn = form.querySelector('.hero-btn');
    const submitLabel = submitBtn.querySelector('.btn-label') || submitBtn;
    const submitLabelDefault = submitLabel.textContent;

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

        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        submitBtn.disabled = true;
        submitLabel.textContent = 'Invio in corso...';

        try {
            const res = await fetch(form.action, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify(Object.fromEntries(new FormData(form)))
            });
            const data = await res.json();
            if (data.success) {
                form.reset();
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

    // Satellite buttons: scroll to the form and focus the email field
    document.querySelectorAll(`[${scrollAttr}]`).forEach((btn) => {
        btn.addEventListener('click', () => {
            form.scrollIntoView({ behavior: 'smooth', block: 'center' });
            emailInput.classList.remove('wl-highlight');
            void emailInput.offsetWidth; // restart animation
            emailInput.classList.add('wl-highlight');
            window.setTimeout(() => emailInput.focus({ preventScroll: true }), 400);
        });
    });
}

initWaitlistForm({ formId: 'waitlist-form', modalId: 'waitlist-modal', emailId: 'waitlist-email', errorId: 'waitlist-error', scrollAttr: 'data-waitlist-scroll' });
initWaitlistForm({ formId: 'demo-form', modalId: 'demo-modal', emailId: 'demo-email', errorId: 'demo-error', scrollAttr: 'data-demo-scroll' });
