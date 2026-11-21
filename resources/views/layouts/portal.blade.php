@extends('layouts.base')

@section('body')
    <div class="min-h-screen flex flex-col max-w-md mx-auto bg-surface">
        <header class="bg-surface-container-lowest border-b border-outline-variant/60 px-gutter-md py-gutter-md flex flex-col gap-gutter-sm">
            @include('partials.logo', ['class' => 'block w-full h-auto'])
            <p class="text-headline-md font-bold text-brand-coral text-center">Portal Pacientes</p>
        </header>

        <main class="flex-1 p-gutter-md flex flex-col gap-gutter-lg">
            @include('partials.flash')
            @yield('content')

            <aside class="bg-surface-container-low rounded-xl p-gutter-lg border border-outline-variant/60 flex flex-col gap-gutter-sm mt-auto">
                <div class="flex items-center gap-gutter-sm">
                    <span class="w-9 h-9 rounded-full bg-brand-pink-container text-on-brand-pink-container flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">support_agent</span>
                    </span>
                    <h2 class="text-title-md text-on-surface">Acceso asistido EIRA</h2>
                </div>
                <p class="text-body-md text-on-surface-variant">
                    Si perdiste tu comprobante, ya no tienes acceso al teléfono o correo que registraste, o prefieres apoyo presencial, acude a tu unidad EIRA. El personal verificará tu identidad antes de entregarte tu documento.
                </p>
                <p class="text-label-md text-on-surface tabular-nums">Línea de orientación: 55 0000 0000 (DEMO)</p>
            </aside>
        </main>
    </div>
@endsection
