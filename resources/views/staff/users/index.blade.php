@extends('layouts.staff')

@section('title', 'Usuarios')
@section('heading', 'Usuarios')
@section('subheading', 'Cuentas individuales del personal · roles verificados en servidor · las cuentas se desactivan, no se borran')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-gutter-md">
        <p class="text-body-md text-on-surface-variant max-w-2xl">
            Operación administrativa carga, asocia y revisa. Responsable de publicación publica y revoca. Administración gestiona cuentas y consulta la bitácora, sin acceso a documentos.
        </p>
        <a href="{{ route('staff.users.create') }}" class="btn btn-primary">
            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">person_add</span>
            Nueva cuenta
        </a>
    </div>

    <div class="bg-surface-container-lowest rounded-xl border border-outline-variant/60 shadow-sm overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-surface-container-low border-b border-outline-variant/60 text-label-md text-on-surface-variant">
                    <th class="px-gutter-md py-gutter-sm">Nombre</th>
                    <th class="px-gutter-md py-gutter-sm">Rol</th>
                    <th class="px-gutter-md py-gutter-sm">Estado</th>
                    <th class="px-gutter-md py-gutter-sm">Último acceso</th>
                    <th class="px-gutter-md py-gutter-sm"><span class="sr-only">Acciones</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                    <tr class="border-b border-outline-variant/40 text-body-md {{ $user->disabled_at ? 'opacity-60' : '' }}">
                        <td class="px-gutter-md py-gutter-sm">
                            <p class="text-label-lg">{{ $user->name }} @if ($user->is(auth()->user())) <span class="badge badge-brand">Tú</span> @endif</p>
                            <p class="text-label-sm text-on-surface-variant">{{ $user->email }}</p>
                        </td>
                        <td class="px-gutter-md py-gutter-sm">{{ $user->roleLabel() }}</td>
                        <td class="px-gutter-md py-gutter-sm">
                            @if ($user->disabled_at)
                                <span class="badge badge-error">Desactivada · {{ \App\Support\Labels::dt($user->disabled_at, 'd/m/Y') }}</span>
                            @else
                                <span class="badge badge-success">Activa</span>
                            @endif
                        </td>
                        <td class="px-gutter-md py-gutter-sm tabular-nums">
                            {{ isset($lastLogins[$user->id]) ? \App\Support\Labels::dt(\Illuminate\Support\Carbon::parse($lastLogins[$user->id])) : 'Nunca' }}
                        </td>
                        <td class="px-gutter-md py-gutter-sm">
                            <div class="flex items-center justify-end gap-gutter-md">
                                <a href="{{ route('staff.users.edit', $user) }}" class="text-label-lg text-primary">Editar</a>
                                @unless ($user->is(auth()->user()))
                                    <form method="POST" action="{{ route('staff.users.toggle', $user) }}">
                                        @csrf
                                        <button type="submit" class="text-label-lg {{ $user->disabled_at ? 'text-primary' : 'text-error' }}">
                                            {{ $user->disabled_at ? 'Reactivar' : 'Desactivar' }}
                                        </button>
                                    </form>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
