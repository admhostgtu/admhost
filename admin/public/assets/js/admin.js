/**
 * JavaScript du panneau d'administration.
 * Confirmations de suppression, interactions UI.
 */

document.addEventListener('DOMContentLoaded', () => {
    // Confirmation avant suppression d'un utilisateur
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', (e) => {
            const message = el.dataset.confirm || 'Êtes-vous sûr ?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
});
