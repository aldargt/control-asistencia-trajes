import './bootstrap';

const navigation = document.querySelector('#main-navigation');
const toggle = document.querySelector('#navigation-toggle');
const backdrop = document.querySelector('#navigation-backdrop');

const setNavigationOpen = (isOpen) => {
    if (! navigation || ! toggle || ! backdrop) return;

    navigation.classList.toggle('hidden', ! isOpen);
    navigation.classList.toggle('flex', isOpen);
    backdrop.classList.toggle('hidden', ! isOpen);
    toggle.setAttribute('aria-expanded', String(isOpen));
};

toggle?.addEventListener('click', () => {
    setNavigationOpen(toggle.getAttribute('aria-expanded') !== 'true');
});

backdrop?.addEventListener('click', () => setNavigationOpen(false));

const weeklyHours = document.querySelector('#weekly_hours');
const jobRole = document.querySelector('#job_role_id');
const agreedSalary = document.querySelector('#monthly_salary');
const suggestedSalary = document.querySelector('#suggested-monthly-salary, #estimated-salary');

const updateSuggestedSalary = () => {
    if (! weeklyHours || ! jobRole || ! agreedSalary || ! suggestedSalary) return;

    const option = jobRole.selectedOptions[0];
    const hours = Number.parseFloat(weeklyHours.value);
    const referenceHours = Number.parseFloat(option?.dataset.referenceHours);
    const referenceSalary = Number.parseFloat(option?.dataset.referenceSalary);
    const previousSuggestion = agreedSalary.dataset.lastSuggestion;

    if (! hours || ! referenceHours || ! referenceSalary) {
        suggestedSalary.textContent = 'Selecciona un rol con referencias e indica las horas semanales.';
        delete agreedSalary.dataset.lastSuggestion;
        return;
    }

    const suggestion = (referenceSalary / referenceHours * hours).toFixed(2);
    suggestedSalary.textContent = new Intl.NumberFormat('es-BO', { style: 'currency', currency: 'BOB' }).format(suggestion);

    if (! agreedSalary.value || agreedSalary.value === previousSuggestion) agreedSalary.value = suggestion;

    agreedSalary.dataset.lastSuggestion = suggestion;
};

weeklyHours?.addEventListener('input', updateSuggestedSalary);
jobRole?.addEventListener('change', updateSuggestedSalary);
updateSuggestedSalary();

document.querySelectorAll('[data-modal-close]').forEach((button) => {
    button.addEventListener('click', () => button.closest('dialog')?.close());
});

document.querySelectorAll('dialog').forEach((dialog) => {
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });
});

const conditionModal = document.querySelector('#condition-modal');
const conditionForm = conditionModal?.querySelector('[data-condition-form]');
const conditionPerson = conditionModal?.querySelector('[data-condition-person]');

document.querySelectorAll('[data-condition-open]').forEach((button) => {
    button.addEventListener('click', () => {
        if (! conditionModal || ! conditionForm) return;

        conditionForm.action = button.dataset.action;
        conditionForm.querySelector('[data-condition-collaborator]').value = button.dataset.collaborator;
        conditionPerson.textContent = button.dataset.person ?? '';
        conditionModal.showModal();
    });
});

if (conditionModal?.hasAttribute('data-open-on-errors')) conditionModal.showModal();

const collaboratorStatusModal = document.querySelector('#collaborator-status-modal');
const collaboratorStatusForm = collaboratorStatusModal?.querySelector('[data-status-form]');
const statusTitle = collaboratorStatusModal?.querySelector('[data-status-title]');
const statusMessage = collaboratorStatusModal?.querySelector('[data-status-message]');
const statusNoteContainer = collaboratorStatusModal?.querySelector('[data-status-note-container]');

document.querySelectorAll('[data-collaborator-status-open]').forEach((button) => {
    button.addEventListener('click', () => {
        if (! collaboratorStatusModal || ! collaboratorStatusForm) return;

        const isActive = button.dataset.active === '1';
        collaboratorStatusForm.action = button.dataset.action;
        statusTitle.textContent = isActive ? 'Desactivar colaborador' : 'Activar colaborador';
        statusMessage.textContent = isActive
            ? `¿Deseas desactivar a ${button.dataset.person}? Sus datos y todo su historial se conservarán.`
            : `¿Deseas activar nuevamente a ${button.dataset.person}? Se iniciará un nuevo período de actividad.`;
        statusNoteContainer.classList.toggle('hidden', ! isActive);
        collaboratorStatusModal.showModal();
    });
});

const roleStatusModal = document.querySelector('#job-role-status-modal');
const roleStatusForm = roleStatusModal?.querySelector('[data-role-status-form]');
const roleStatusTitle = roleStatusModal?.querySelector('[data-role-status-title]');
const roleStatusMessage = roleStatusModal?.querySelector('[data-role-status-message]');

document.querySelectorAll('[data-role-status-open]').forEach((button) => {
    button.addEventListener('click', () => {
        if (! roleStatusModal || ! roleStatusForm) return;

        const isActive = button.dataset.active === '1';
        roleStatusForm.action = button.dataset.action;
        roleStatusTitle.textContent = isActive ? 'Desactivar rol laboral' : 'Activar rol laboral';
        roleStatusMessage.textContent = `¿Deseas ${isActive ? 'desactivar' : 'activar'} el rol laboral ${button.dataset.role}?`;
        roleStatusModal.showModal();
    });
});
