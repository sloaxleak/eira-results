const sidebar = document.querySelector('[data-sidebar]');
const backdrop = document.querySelector('[data-sidebar-backdrop]');

document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        sidebar?.classList.toggle('-translate-x-full');
        backdrop?.classList.toggle('hidden');
    });
});

document.querySelectorAll('[data-file-input]').forEach((input) => {
    const target = document.querySelector(`[data-file-name="${input.dataset.fileInput}"]`);
    const hint = target?.textContent;
    input.addEventListener('change', () => {
        if (target) {
            target.textContent = input.files[0]?.name ?? hint;
            target.classList.toggle('text-primary', Boolean(input.files[0]));
        }
    });
});
