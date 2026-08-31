<dialog id="collaborator-status-modal" class="fixed inset-0 m-auto w-[min(30rem,calc(100%-2rem))] rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-950/60">
    <form data-status-form method="POST" class="p-5 sm:p-7">@csrf @method('PATCH')
        <h2 class="text-xl font-bold" data-status-title>Confirmar cambio de estado</h2>
        <p class="mt-3 text-sm leading-6 text-slate-600" data-status-message></p>
        <div data-status-note-container class="mt-5"><label for="status_note" class="mb-2 block text-sm font-medium">Observaciones <span class="text-slate-400">(opcional)</span></label><textarea id="status_note" name="status_note" rows="3" class="field" placeholder="Motivo o información relevante de la desactivación"></textarea></div>
        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"><button type="button" data-modal-close class="button-secondary">Cancelar</button><button type="submit" class="button-primary" data-status-confirm>Confirmar</button></div>
    </form>
</dialog>
