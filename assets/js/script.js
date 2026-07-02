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