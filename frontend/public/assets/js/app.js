/**
 * JavaScript du Frontend public.
 * Interactions côté client (validation, UX).
 */

document.addEventListener('DOMContentLoaded', () => {
    // Validation basique des formulaires avant soumission
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', (e) => {
            const required = form.querySelectorAll('[required]');
            let valid = true;

            required.forEach(field => {
                if (!field.value.trim()) {
                    field.style.borderColor = '#dc2626';
                    valid = false;
                } else {
                    field.style.borderColor = '#cbd5e1';
                }
            });

            if (!valid) {
                e.preventDefault();
            }
        });
    });
});
