// Fenêtre « Étudiant » de la barre du haut : un <dialog> natif (focus piégé, Échap) qui propose de choisir entre les services en
// ligne (Registrar, Moodle). Fond cliquable pour fermer ; le défilement fluide est suspendu pendant l'ouverture.
export function initStudentDialog() {
    const dialog = document.querySelector('[data-student-dialog]');
    const triggers = document.querySelectorAll('[data-student-open]');
    if (!dialog || !triggers.length || typeof dialog.showModal !== 'function') return;

    const lock = (open) => {
        document.documentElement.classList.toggle('profile-open', open);
        window.dispatchEvent(new CustomEvent('uaz:search', { detail: { open } }));
    };

    triggers.forEach((trigger) =>
        trigger.addEventListener('click', () => {
            dialog.showModal();
            lock(true);
        }),
    );
    dialog.querySelector('[data-student-close]')?.addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });
    dialog.addEventListener('close', () => lock(false));
}
