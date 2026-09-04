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
toggle?.addEventListener('click', () => setNavigationOpen(toggle.getAttribute('aria-expanded') !== 'true'));
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

document.querySelectorAll('[data-modal-close]').forEach((button) => button.addEventListener('click', () => button.closest('dialog')?.close()));
document.querySelectorAll('dialog').forEach((dialog) => dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); }));

const conditionModal = document.querySelector('#condition-modal');
const conditionForm = conditionModal?.querySelector('[data-condition-form]');
const conditionPerson = conditionModal?.querySelector('[data-condition-person]');
document.querySelectorAll('[data-condition-open]').forEach((button) => button.addEventListener('click', () => {
    if (! conditionModal || ! conditionForm) return;
    conditionForm.action = button.dataset.action;
    conditionForm.querySelector('[data-condition-collaborator]').value = button.dataset.collaborator;
    conditionPerson.textContent = button.dataset.person ?? '';
    conditionModal.showModal();
}));
if (conditionModal?.hasAttribute('data-open-on-errors')) conditionModal.showModal();

const correctionModal = document.querySelector('#attendance-correction-modal');
const correctionForm = correctionModal?.querySelector('[data-correction-form]');
const correctionMarks = correctionModal?.querySelector('[data-correction-marks]');
const manualMarks = correctionModal?.querySelector('[data-manual-marks]');
const correctionPreview = correctionModal?.querySelector('[data-correction-preview]');
const correctionErrors = correctionModal?.querySelector('[data-correction-errors]');
let activeCorrectionData = null;

const showCorrectionErrors = (messages) => {
    correctionErrors.textContent = '';
    correctionErrors.classList.toggle('hidden', ! messages.length);
    if (! messages.length) return;
    const title = document.createElement('p');
    title.className = 'font-semibold';
    title.textContent = 'No se pudo guardar la corrección:';
    correctionErrors.append(title);
    messages.forEach((message) => { const error = document.createElement('p'); error.className = 'mt-1'; error.textContent = message; correctionErrors.append(error); });
};

const updateCorrectionPreview = () => {
    if (! correctionForm || ! correctionPreview) return;
    const selected = [...correctionForm.querySelectorAll('input[name="selected_marks[]"]:checked')]
        .map((input) => ({ value: input.dataset.occurredAt, label: input.dataset.label }));
    const manual = [...correctionForm.querySelectorAll('input[name="manual_mark_times[]"]')]
        .filter((input) => input.value)
        .map((input) => {
            const nextMorning = input.value < '06:00';
            return { value: `${nextMorning ? activeCorrectionData.nextWorkDate : activeCorrectionData.workDate}T${input.value}`, label: `${input.value} · ${nextMorning ? 'madrugada siguiente' : 'este día'} (manual)` };
        });
    const marks = [...selected, ...manual].sort((left, right) => left.value.localeCompare(right.value));
    if (marks.length < 2 || marks.length % 2 !== 0) {
        correctionPreview.textContent = 'Selecciona una cantidad par de marcaciones, con un mínimo de dos.';
        return;
    }
    correctionPreview.textContent = '';
    for (let index = 0; index < marks.length; index += 2) {
        const pair = document.createElement('p');
        pair.textContent = `${marks[index].label} → ${marks[index + 1].label}`;
        correctionPreview.append(pair);
    }
};

const addManualMark = (mark = { time: '' }) => {
    if (! manualMarks) return;
    const row = document.createElement('div');
    row.dataset.manualRow = '';
    row.className = 'flex items-end gap-2 rounded-xl border border-slate-200 p-3';
    row.innerHTML = `<label class="flex-1 text-sm font-semibold">Hora<input type="time" name="manual_mark_times[]" value="${mark.time ?? ''}" class="field mt-2" required></label><button type="button" class="button-secondary whitespace-nowrap">Quitar</button>`;
    row.querySelector('button').addEventListener('click', () => { row.remove(); updateCorrectionPreview(); });
    row.querySelectorAll('input').forEach((input) => input.addEventListener('input', updateCorrectionPreview));
    manualMarks.append(row);
};

const openCorrection = (button) => {
    if (! correctionModal || ! correctionForm || ! correctionMarks || ! manualMarks) return;
    const data = JSON.parse(document.querySelector(`#${button.dataset.source}`).textContent);
    activeCorrectionData = data;
    correctionForm.action = data.action;
    correctionModal.querySelector('[data-correction-person]').textContent = data.person;
    correctionModal.querySelector('[data-correction-date]').textContent = data.date;
    correctionModal.querySelector('[data-correction-status]').textContent = data.status;
    correctionForm.querySelector('[name="notes"]').value = data.notes ?? '';
    correctionForm.querySelector('[data-correction-id]').value = data.id;
    correctionMarks.textContent = '';
    manualMarks.textContent = '';
    showCorrectionErrors(data.errors);
    data.marks.forEach((mark) => {
        const label = document.createElement('label');
        label.className = 'flex items-center gap-3 rounded-xl border border-slate-200 p-3 text-sm hover:bg-slate-50';
        label.innerHTML = `<input type="checkbox" name="selected_marks[]" value="${mark.id}" data-label="${mark.label}" data-occurred-at="${mark.occurredAt}" class="size-4 rounded border-slate-300" ${mark.selected ? 'checked' : ''}><span class="text-base font-semibold">${mark.label}</span>`;
        label.querySelector('input').addEventListener('change', updateCorrectionPreview);
        correctionMarks.append(label);
    });
    data.manual.forEach(addManualMark);
    correctionModal.querySelector('[data-undo-container]').classList.toggle('hidden', ! data.corrected);
    correctionModal.querySelector('[data-undo-form]').action = data.undoAction;
    updateCorrectionPreview();
    correctionModal.showModal();
};

document.querySelectorAll('[data-correction-open]').forEach((button) => button.addEventListener('click', () => openCorrection(button)));
document.querySelector('[data-add-manual-mark]')?.addEventListener('click', () => { addManualMark(); updateCorrectionPreview(); });
correctionForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const submitButton = correctionForm.querySelector('[type="submit"]');
    submitButton.disabled = true;
    try {
        const response = await fetch(correctionForm.action, { method: 'POST', body: new FormData(correctionForm), headers: { Accept: 'application/json' } });
        if (response.status === 422) {
            const payload = await response.json();
            showCorrectionErrors(Object.values(payload.errors ?? {}).flat());
            return;
        }
        if (! response.ok) throw new Error('request_failed');
        correctionModal.close();
        window.location.reload();
    } catch {
        showCorrectionErrors(['No se pudo guardar la corrección. Inténtalo nuevamente.']);
    } finally {
        submitButton.disabled = false;
    }
});
document.querySelector('[data-undo-correction]')?.addEventListener('click', async () => {
    if (! window.confirm('¿Deseas deshacer esta corrección y restaurar la interpretación automática?')) return;
    const undoForm = correctionModal.querySelector('[data-undo-form]');
    try {
        const response = await fetch(undoForm.action, { method: 'POST', body: new FormData(undoForm), headers: { Accept: 'application/json' } });
        if (! response.ok) throw new Error('request_failed');
        window.location.reload();
    } catch {
        showCorrectionErrors(['No se pudo deshacer la corrección. Inténtalo nuevamente.']);
    }
});
const correctionToReopen = [...document.querySelectorAll('[data-correction-open]')]
    .find((button) => JSON.parse(document.querySelector(`#${button.dataset.source}`).textContent).open);
if (correctionToReopen) {
    openCorrection(correctionToReopen);
    const currentUrl = new URL(window.location.href);
    if (currentUrl.searchParams.has('interpretation_id')) {
        currentUrl.searchParams.delete('interpretation_id');
        window.history.replaceState({}, '', currentUrl);
    }
}

const calculationDayModal = document.querySelector('#calculation-day-modal');
const calculationDayContent = calculationDayModal?.querySelector('[data-calculation-day-content]');
document.querySelectorAll('[data-calculation-day-open]').forEach((button) => button.addEventListener('click', () => {
    const template = document.querySelector(`#${button.dataset.source}`);
    if (! calculationDayModal || ! calculationDayContent || ! template) return;
    calculationDayContent.replaceChildren(template.content.cloneNode(true));
    calculationDayModal.showModal();
}));

const collaboratorStatusModal = document.querySelector('#collaborator-status-modal');
const collaboratorStatusForm = collaboratorStatusModal?.querySelector('[data-status-form]');
const statusTitle = collaboratorStatusModal?.querySelector('[data-status-title]');
const statusMessage = collaboratorStatusModal?.querySelector('[data-status-message]');
const statusNoteContainer = collaboratorStatusModal?.querySelector('[data-status-note-container]');
document.querySelectorAll('[data-collaborator-status-open]').forEach((button) => button.addEventListener('click', () => {
    if (! collaboratorStatusModal || ! collaboratorStatusForm) return;
    const isActive = button.dataset.active === '1';
    collaboratorStatusForm.action = button.dataset.action;
    statusTitle.textContent = isActive ? 'Desactivar colaborador' : 'Activar colaborador';
    statusMessage.textContent = isActive ? `¿Deseas desactivar a ${button.dataset.person}? Sus datos y todo su historial se conservarán.` : `¿Deseas activar nuevamente a ${button.dataset.person}? Se iniciará un nuevo período de actividad.`;
    statusNoteContainer.classList.toggle('hidden', ! isActive);
    collaboratorStatusModal.showModal();
}));

const roleStatusModal = document.querySelector('#job-role-status-modal');
const roleStatusForm = roleStatusModal?.querySelector('[data-role-status-form]');
const roleStatusTitle = roleStatusModal?.querySelector('[data-role-status-title]');
const roleStatusMessage = roleStatusModal?.querySelector('[data-role-status-message]');
document.querySelectorAll('[data-role-status-open]').forEach((button) => button.addEventListener('click', () => {
    if (! roleStatusModal || ! roleStatusForm) return;
    const isActive = button.dataset.active === '1';
    roleStatusForm.action = button.dataset.action;
    roleStatusTitle.textContent = isActive ? 'Desactivar rol laboral' : 'Activar rol laboral';
    roleStatusMessage.textContent = `¿Deseas ${isActive ? 'desactivar' : 'activar'} el rol laboral ${button.dataset.role}?`;
    roleStatusModal.showModal();
}));
