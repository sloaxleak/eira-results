@extends('layouts.base')

@section('title', 'Acceso de personal')

@section('body')
    <div class="min-h-screen flex items-center justify-center p-gutter-md">
        <div class="w-full max-w-md flex flex-col gap-gutter-lg">
            <div class="flex items-center gap-gutter-sm">
                @include('partials.logo')
                <span class="badge badge-brand">Resultados</span>
                <span class="badge badge-neutral">DEMO</span>
            </div>

            <div>
                <h1 class="text-display-lg-mobile text-on-surface">Acceso de personal</h1>
                <p class="text-body-md text-on-surface-variant">Cuentas individuales. Cada acción queda registrada en la bitácora.</p>
            </div>

            @include('partials.flash')

            <form method="POST" action="{{ route('login.attempt') }}" class="card flex flex-col gap-gutter-md">
                @csrf
                <label class="flex flex-col gap-gutter-xs">
                    <span class="text-label-md text-on-surface">Correo</span>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="field">
                </label>
                <label class="flex flex-col gap-gutter-xs">
                    <span class="text-label-md text-on-surface">Contraseña</span>
                    <input type="password" name="password" required autocomplete="current-password" class="field">
                </label>
                <button type="submit" class="btn btn-primary w-full">
                    <span class="material-symbols-outlined text-[20px]" aria-hidden="true">login</span>
                    Entrar
                </button>
            </form>

            @include('partials.demo-switch', ['title' => 'Entrar con cuenta DEMO (sin contraseña, solo demo)', 'class' => 'card'])

            <a href="{{ route('portal.show') }}" class="text-label-lg text-primary text-center">Soy paciente: consultar mi documento</a>
        </div>
    </div>
@endsection
