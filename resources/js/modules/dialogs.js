document.querySelectorAll('[data-open-dialog]').forEach(button => {
    button.addEventListener('click', () => document.getElementById(button.dataset.openDialog)?.showModal());
});
document.querySelectorAll('[data-close-dialog]').forEach(button => {
    button.addEventListener('click', () => button.closest('dialog')?.close());
});
document.querySelectorAll('dialog').forEach(dialog => {
    dialog.addEventListener('click', event => {
        const rect = dialog.getBoundingClientRect();
        if (event.target === dialog && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom)) dialog.close();
    });
});
document.addEventListener('click', event => {
    document.querySelectorAll('.category-menu[open]').forEach(menu => { if (!menu.contains(event.target)) menu.open = false; });
});
document.addEventListener('keydown', event => {
    if (event.key === 'Escape') document.querySelectorAll('.category-menu[open]').forEach(menu => { menu.open = false; });
});
// Mobile filter disclosure; navigation and filters still work without JavaScript.
const filter = document.querySelector('.filter-details');
if (filter && window.matchMedia('(max-width:650px)').matches) filter.open = false;
