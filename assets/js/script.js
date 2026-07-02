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

// Waitlist form — submit via Web3Forms, confirmation modal, satellite buttons scroll to form
(function () {
    const form = document.getElementById('waitlist-form');
    const modal = document.getElementById('waitlist-modal');
    if (!form || !modal) return;

    const emailInput = document.getElementById('waitlist-email');
    const errorBox = document.getElementById('waitlist-error');
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

    // Satellite buttons: scroll to the waitlist form and focus the email field
    document.querySelectorAll('[data-waitlist-scroll]').forEach((btn) => {
        btn.addEventListener('click', () => {
            form.scrollIntoView({ behavior: 'smooth', block: 'center' });
            emailInput.classList.remove('wl-highlight');
            void emailInput.offsetWidth; // restart animation
            emailInput.classList.add('wl-highlight');
            window.setTimeout(() => emailInput.focus({ preventScroll: true }), 400);
        });
    });
})();