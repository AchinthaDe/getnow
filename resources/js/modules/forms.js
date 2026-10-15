document.querySelectorAll('[data-toggle-password]').forEach(button => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.togglePassword);
        if (!input) return;
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        button.setAttribute('aria-pressed', String(show));
    });
});
document.querySelector('[data-demo-login]')?.addEventListener('click', event => {
    const button = event.currentTarget;
    document.getElementById('email').value = button.dataset.email;
    document.getElementById('password').value = button.dataset.password;
    document.getElementById('email').focus();
});
document.querySelectorAll('[data-submit-on-change]').forEach(select => select.addEventListener('change', () => select.form.requestSubmit()));
document.querySelectorAll('[data-dismiss-notice]').forEach(button => button.addEventListener('click', () => button.closest('.notice').remove()));
document.querySelectorAll('form[data-submit-once]').forEach(form => {
    form.addEventListener('submit', event => {
        if (form.dataset.submitting === 'true') { event.preventDefault(); return; }
        form.dataset.submitting = 'true';
        form.querySelectorAll('button:not([type="button"])').forEach(button => {
            button.dataset.originalText = button.textContent;
            button.disabled = true;
            button.textContent = 'Please wait…';
        });
    });
});
window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-submit-once]').forEach(form => {
        delete form.dataset.submitting;
        form.querySelectorAll('button[data-original-text]').forEach(button => {
            button.disabled = false; button.textContent = button.dataset.originalText;
        });
    });
});
// Local fallback keeps missing remote catalog images from breaking the layout.
document.querySelectorAll('img').forEach(img => {
    img.addEventListener('error', () => {
        if (img.dataset.fallback) return;
        img.dataset.fallback = 'true'; img.src = '/images/placeholder.svg';
    });
});
