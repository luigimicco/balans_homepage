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

// Toggle Demo Interattiva (sezione #funzionalita)
document.addEventListener('click', (e) => {
    const btn = e.target.closest('.demo-toggle');
    if (!btn) return;
    const section = btn.closest('#funzionalita');
    const isDemo = section.getAttribute('data-mode') === 'demo';
    section.setAttribute('data-mode', isDemo ? 'normal' : 'demo');
    btn.setAttribute('aria-pressed', String(!isDemo));
});

// Demo interattiva — menu categorie + risposte simulate in chat
(function () {
    const cats = document.querySelector('.demo-cats');
    const phone = document.querySelector('#funzionalita .phone');
    if (!cats || !phone) return;

    const welcome = phone.querySelector('.demo-welcome');
    const chat = phone.querySelector('.demo-chat');
    const REDUCED = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const ANSWERS = {
        c1q1: '<div class="bub ai">Ad oggi hai fatturato <b>€45.000</b>. Puoi fatturare ancora <b>€40.000</b> prima di raggiungere la soglia limite di €85.000 per il regime forfettario.</div>',
        c1q2: '<div class="bub ai">Il tuo fatturato totale aggiornato è di <b>€45.000</b> complessivi, generato da un totale di <b>12 fatture</b> saldate.</div>',
        c1q3: '<div class="bub ai">Ecco il trend dei tuoi incassi degli ultimi 3 mesi</div>'
            + '<div class="bub ai-card">'
            + '<div class="h"><img src="./assets/img/icons/vettoriale/balance.svg" alt="" style="width:11px; height:11px; display:inline-block; flex-shrink:0; filter:brightness(0) saturate(100%) invert(40%) sepia(30%) saturate(1000%) hue-rotate(120deg) brightness(90%);"> Incassi trimestre</div>'
            + '<div class="mini-chart">'
            + '<div class="mc-bar"><span class="mc-v">€4.230</span><span class="mc-fill" style="height:48px"></span><span class="mc-x">Apr</span></div>'
            + '<div class="mc-bar"><span class="mc-v">€5.100</span><span class="mc-fill" style="height:58px"></span><span class="mc-x">Mag</span></div>'
            + '<div class="mc-bar"><span class="mc-v">€6.400</span><span class="mc-fill" style="height:72px"></span><span class="mc-x">Giu</span></div>'
            + '</div>'
            + '</div>',
        c2q1: '<div class="bub ai-card" style="max-width:88%">'
            + '<div class="h"><img src="./assets/img/icons/vettoriale/doc.svg" alt="" style="width:11px; height:11px; display:inline-block; flex-shrink:0; filter:brightness(0) saturate(100%) invert(40%) sepia(30%) saturate(1000%) hue-rotate(120deg) brightness(90%);"> Fattura pronta</div>'
            + '<div class="row"><span class="k">Cliente</span><span class="v">Studio Bianchi</span></div>'
            + '<div class="row"><span class="k">Causale</span><span class="v">Consulenza</span></div>'
            + '<div class="row"><span class="k">Importo</span><span class="v">€ 500,00</span></div>'
            + '<button class="demo-invia" type="button">Invia fattura</button>'
            + '</div>'
            + '<div class="bub ai">Ho preparato la fattura per Studio Bianchi di €500,00 con causale «Consulenza». Clicca su <b>Invia</b> per inoltrarla.</div>',
        c2q2: '<div class="bub ai">Nota di credito numero <b>12-A</b> generata per un importo di <b>€150,00</b>, stornata a favore del cliente. Pronta per l\'invio al Sistema di Interscambio.</div>',
        c2q3: '<div class="bub ai">Sì: trattandosi di una fattura in regime forfettario (esente IVA) con un importo superiore a €77,47, l\'imposta di bollo virtuale da <b>€2,00</b> è obbligatoria. L\'ho già aggiunta automaticamente.</div>',
        c3q1: '<div class="bub ai">Per la scadenza di giugno l\'acconto e saldo stimati ammontano a <b>€3.450</b> di Gestione Separata INPS e <b>€1.200</b> di Imposta Sostitutiva.</div>',
        c3q2: '<div class="bub ai">Considerando il tuo coefficiente di redditività, ti consiglio di accantonare circa il <b>25%</b> dell\'incassato (quindi <b>€750</b>) per coprire comodamente tasse e contributi a saldo.</div>',
        c3q3: '<div class="bub ai">I tuoi contributi previdenziali calcolati sul fatturato dell\'anno in corso ammontano a circa <b>€4.820</b>.</div>',
        c4q1: '<div class="bub ai">Nel regime forfettario le spese reali non si scaricano analiticamente. Le tue spese sono già dedotte in modo forfettario tramite il tuo coefficiente di redditività (es. 67%), quindi non serve registrare la singola fattura del computer per scaricarla.</div>',
        c4q2: '<div class="bub ai">Come per il computer, nel regime forfettario le spese di rappresentanza o pasto non modificano il calcolo delle tasse: la deduzione avviene a monte sulla base del tuo coefficiente.</div>'
    };

    function scrollBottom() {
        chat.scrollTop = chat.scrollHeight;
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

    // Accordion sulle pillole di categoria
    cats.querySelectorAll('.cat-pill').forEach(pill => {
        pill.addEventListener('click', () => {
            const group = pill.closest('.cat-group');
            const wasOpen = group.classList.contains('open');
            cats.querySelectorAll('.cat-group').forEach(g => g.classList.remove('open'));
            if (!wasOpen) group.classList.add('open');
        });
    });

    // Invio di una domanda predefinita
    let busy = false;
    cats.querySelectorAll('.cat-q').forEach(q => {
        q.addEventListener('click', () => {
            if (busy) return;
            busy = true;

            welcome.classList.add('hidden');
            chat.classList.add('active');

            const bubble = document.createElement('div');
            bubble.className = 'bub me';
            bubble.textContent = q.textContent.trim();
            chat.appendChild(bubble);
            popIn(bubble);
            scrollBottom();

            const [typing] = appendHTML('<div class="typing-bub"><span></span><span></span><span></span></div>');
            scrollBottom();

            setTimeout(() => {
                typing.remove();
                const nodes = appendHTML(ANSWERS[q.dataset.key] || '<div class="bub ai">…</div>');
                nodes.forEach((n, i) => popIn(n, i * 90));
                scrollBottom();
                busy = false;
            }, REDUCED ? 0 : 550);
        });
    });
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
