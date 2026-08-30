@php($editing = isset($user))

<div class="grid gap-6 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label for="name" class="mb-2 block text-sm font-medium">Nombre completo</label>
        <input id="name" name="name" type="text" value="{{ old('name', $user->name ?? '') }}" autocomplete="name" required
            class="w-full rounded-xl border border-slate-300 px-3.5 py-3 text-sm outline-none focus:border-slate-700 focus:ring-4 focus:ring-slate-100 @error('name') border-red-500 @enderror">
        @error('name')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div class="sm:col-span-2">
        <label for="email" class="mb-2 block text-sm font-medium">Correo electrónico</label>
        <input id="email" name="email" type="email" value="{{ old('email', $user->email ?? '') }}" autocomplete="email" required
            class="w-full rounded-xl border border-slate-300 px-3.5 py-3 text-sm outline-none focus:border-slate-700 focus:ring-4 focus:ring-slate-100 @error('email') border-red-500 @enderror">
        @error('email')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="role" class="mb-2 block text-sm font-medium">Rol</label>
        <select id="role" name="role" required class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-3 text-sm outline-none focus:border-slate-700 focus:ring-4 focus:ring-slate-100">
            <option value="administrator" @selected(old('role', $user->role ?? 'administrator') === 'administrator')>Administrador</option>
        </select>
        <p class="mt-1.5 text-xs text-slate-500">Las cuentas creadas desde el panel son administradores secundarios.</p>
        @error('role')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <span class="mb-2 block text-sm font-medium">Estado de la cuenta</span>
        <input type="hidden" name="is_active" value="0">
        <label class="flex min-h-12 items-center gap-3 rounded-xl border border-slate-300 px-3.5 py-3 text-sm">
            <input name="is_active" type="checkbox" value="1" class="size-4 rounded border-slate-300 text-slate-950 focus:ring-slate-500" @checked((bool) old('is_active', $user->is_active ?? true))>
            Cuenta activa
        </label>
        @error('is_active')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="password" class="mb-2 block text-sm font-medium">{{ $editing ? 'Nueva contraseña' : 'Contraseña' }}</label>
        <input id="password" name="password" type="password" autocomplete="new-password" @required(! $editing)
            class="w-full rounded-xl border border-slate-300 px-3.5 py-3 text-sm outline-none focus:border-slate-700 focus:ring-4 focus:ring-slate-100 @error('password') border-red-500 @enderror">
        <p class="mt-1.5 text-xs text-slate-500">{{ $editing ? 'Déjala vacía para conservar la contraseña actual.' : 'Mínimo ocho caracteres.' }}</p>
        @error('password')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="password_confirmation" class="mb-2 block text-sm font-medium">Confirmar contraseña</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" @required(! $editing) class="w-full rounded-xl border border-slate-300 px-3.5 py-3 text-sm outline-none focus:border-slate-700 focus:ring-4 focus:ring-slate-100">
    </div>
</div>

<div class="mt-8 flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:justify-end">
    <a href="{{ route('users.index') }}" class="rounded-xl border border-slate-300 px-5 py-2.5 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</a>
    <button type="submit" class="rounded-xl bg-slate-950 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">{{ $editing ? 'Guardar cambios' : 'Crear usuario' }}</button>
</div>
