@extends('layouts.staff')

@php $editing = $user->exists; @endphp

@section('title', $editing ? 'Editar cuenta' : 'Nueva cuenta')
@section('heading', $editing ? 'Editar cuenta' : 'Nueva cuenta')
@section('subheading', $editing ? $user->email : 'Una cuenta por persona; no compartir credenciales')

@section('content')
    <form method="POST" action="{{ $editing ? route('staff.users.update', $user) : route('staff.users.store') }}" class="card flex flex-col gap-gutter-md max-w-xl" autocomplete="off">
        @csrf
        @if ($editing) @method('PUT') @endif

        <label class="flex flex-col gap-gutter-xs">
            <span class="text-label-md text-on-surface">Nombre</span>
            <input type="text" name="name" value="{{ old('name', $user->name) }}" required maxlength="120" class="field">
        </label>

        <label class="flex flex-col gap-gutter-xs">
            <span class="text-label-md text-on-surface">Correo</span>
            <input type="email" name="email" value="{{ old('email', $user->email) }}" required maxlength="120" class="field">
        </label>

        <label class="flex flex-col gap-gutter-xs">
            <span class="text-label-md text-on-surface">Rol</span>
            <select name="role" class="field" @disabled($editing && $user->is(auth()->user()))>
                @foreach (\App\Models\User::roleOptions() as $value => $label)
                    <option value="{{ $value }}" @selected(old('role', $user->role) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @if ($editing && $user->is(auth()->user()))
                <input type="hidden" name="role" value="{{ $user->role }}">
                <span class="text-label-sm text-on-surface-variant">No puedes cambiar tu propio rol.</span>
            @endif
            <span class="text-label-sm text-on-surface-variant">Supuesto del demo: quién tiene facultad de publicación está pendiente de confirmar con EIRA.</span>
        </label>

        <fieldset class="flex flex-col gap-gutter-sm border-t border-outline-variant/60 pt-gutter-md">
            <legend class="text-title-md text-on-surface">{{ $editing ? 'Restablecer contraseña (opcional)' : 'Contraseña inicial' }}</legend>
            <label class="flex flex-col gap-gutter-xs">
                <span class="text-label-md text-on-surface">Contraseña</span>
                <input type="password" name="password" @required(! $editing) minlength="12" autocomplete="new-password" class="field">
            </label>
            <label class="flex flex-col gap-gutter-xs">
                <span class="text-label-md text-on-surface">Confirmar contraseña</span>
                <input type="password" name="password_confirmation" @required(! $editing) minlength="12" autocomplete="new-password" class="field">
            </label>
            <span class="text-label-sm text-on-surface-variant">Mínimo 12 caracteres. Comunícala por un canal distinto al correo de la cuenta.</span>
        </fieldset>

        <div class="flex gap-gutter-sm justify-end">
            <a href="{{ route('staff.users.index') }}" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">{{ $editing ? 'Guardar cambios' : 'Crear cuenta' }}</button>
        </div>
    </form>
@endsection
