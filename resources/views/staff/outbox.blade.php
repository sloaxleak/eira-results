@extends('layouts.staff')

@section('title', 'Bandeja simulada')
@section('heading', 'Bandeja simulada (solo demo)')
@section('subheading', 'Avisos que los transportes fake habrían enviado · no existe con transportes reales ni en producción')

@section('content')
    <div class="p-gutter-md rounded-md bg-warning-container text-on-warning-container text-body-md">
        SIMULADO. Esta bandeja sustituye al teléfono o correo de la paciente durante la demo. Los mensajes se conservan en caché solo mientras el código está vigente y nunca se escriben en logs. En piloto se desactiva (<code>EIRA_DEMO_OUTBOX=false</code>).
    </div>

    <a href="{{ route('staff.email-preview') }}" target="_blank" rel="noopener" class="btn btn-secondary self-start">
        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">mail</span>
        Ver diseño del correo de aviso
    </a>

    <section class="flex flex-col gap-gutter-md">
        @forelse ($messages as $message)
            <article class="card flex flex-col gap-gutter-sm">
                <div class="flex flex-wrap items-center gap-gutter-sm">
                    <span class="badge badge-brand uppercase">{{ $message['channel'] }}</span>
                    @include('partials.badge', ['badge' => \App\Support\Labels::notification($message['status'])])
                    <span class="text-label-sm text-on-surface-variant tabular-nums">{{ $message['to'] }} · {{ \Illuminate\Support\Carbon::createFromTimestamp($message['at'])->timezone(config('eira.display_timezone'))->format('H:i:s') }}</span>
                </div>
                <p class="text-body-lg text-on-surface">{{ $message['text'] }}</p>
            </article>
        @empty
            <div class="card text-body-md text-on-surface-variant">Sin avisos vigentes. Publica un documento o solicita un código nuevo desde el portal.</div>
        @endforelse
    </section>
@endsection
