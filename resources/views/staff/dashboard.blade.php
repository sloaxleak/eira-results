@extends('layouts.staff')

@section('title', 'Panel de resultados')
@section('heading', 'Panel de resultados')
@section('subheading', 'Seguimiento operativo sin diagnósticos · documento, aviso y consulta son estados independientes')

@section('content')
    @php
        $cards = [
            ['Por asociar / revisar', ($byStatus['pending_association'] ?? 0) + ($byStatus['pending_review'] ?? 0), 'Pendientes de revisión administrativa', 'fact_check', 'text-primary', 'bg-surface-container-low text-primary'],
            ['Por publicar', $byStatus['ready_for_publication'] ?? 0, 'Revisados, en espera de decisión', 'pending_actions', 'text-warning', 'bg-warning-container text-on-warning-container'],
            ['Publicados', $byStatus['published'] ?? 0, 'Disponibles para consulta', 'verified', 'text-success', 'bg-success-container text-on-success-container'],
            ['Consultados', $consultedStudies, 'Estudios vistos o descargados', 'download_done', 'text-info', 'bg-info-container text-on-info-container'],
        ];
    @endphp

    <section aria-label="Contadores de flujo" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-gutter-md items-stretch">
        @foreach ($cards as [$title, $value, $caption, $icon, $tone, $chip])
            <article class="card h-full flex flex-col justify-between gap-gutter-sm">
                <header class="flex items-center justify-between gap-gutter-sm">
                    <h2 class="text-title-md text-on-surface">{{ $title }}</h2>
                    <span class="w-9 h-9 rounded-full {{ $chip }} flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">{{ $icon }}</span>
                    </span>
                </header>
                <div>
                    <p class="text-display-lg {{ $tone }} tabular-nums">{{ $value }}</p>
                    <p class="text-body-md text-on-surface-variant">{{ $caption }}</p>
                </div>
            </article>
        @endforeach
    </section>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-gutter-lg items-start">
        <section class="xl:col-span-8 flex flex-col gap-gutter-md">
            <div>
                <h2 class="text-title-lg text-on-surface">Seguimiento de estudios</h2>
                <p class="text-body-md text-on-surface-variant">{{ $consultedStudies }} estudio(s) con eventos de consulta. «Aceptado» no significa «entregado»; «consultado» no implica que la paciente leyó o comprendió.</p>
            </div>

            <div class="bg-surface-container-lowest rounded-xl border border-outline-variant/60 shadow-sm overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface-container-low border-b border-outline-variant/60 text-label-md text-on-surface-variant">
                            <th class="px-gutter-md py-gutter-sm">Folio del formato</th>
                            <th class="px-gutter-md py-gutter-sm">Documento</th>
                            <th class="px-gutter-md py-gutter-sm">Avisos</th>
                            <th class="px-gutter-md py-gutter-sm">Consulta</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($studies as $study)
                            @php
                                $current = $study->documents->firstWhere('type', 'result') ?? $study->documents->first();
                                $studyEvents = $consultations->get($study->id, collect())->keyBy('action');
                                $lastByChannel = $study->notifications->sortBy('updated_at')->keyBy('channel');
                            @endphp
                            <tr class="border-b border-outline-variant/40 text-body-md align-top">
                                <td class="px-gutter-md py-gutter-sm">
                                    <p class="text-label-lg tabular-nums">{{ $study->external_form_folio ?? '—' }}</p>
                                    <p class="text-label-sm text-on-surface-variant tabular-nums">Interno {{ $study->folio }} · {{ $study->documents->count() }} documento(s)</p>
                                </td>
                                <td class="px-gutter-md py-gutter-sm">
                                    <a href="{{ route('staff.documents.show', $current) }}" class="inline-flex flex-col gap-gutter-xs">
                                        @include('partials.badge', ['badge' => \App\Support\Labels::document($current->status)])
                                        <span class="text-label-sm text-primary">{{ $current->typeLabel() }} v{{ $current->version }} · abrir</span>
                                    </a>
                                </td>
                                <td class="px-gutter-md py-gutter-sm">
                                    <div class="flex flex-col gap-gutter-xs">
                                        @forelse ($lastByChannel as $channel => $notification)
                                            <span class="inline-flex items-center gap-gutter-xs">
                                                <span class="text-label-sm text-on-surface-variant uppercase w-10">{{ $channel }}</span>
                                                @include('partials.badge', ['badge' => \App\Support\Labels::notification($notification->status)])
                                            </span>
                                        @empty
                                            @include('partials.badge', ['badge' => \App\Support\Labels::notification(null)])
                                            @if ($study->contacts->whereNotNull('verified_at')->isEmpty())
                                                <span class="text-label-sm text-on-surface-variant">Sin contacto verificado · atención asistida</span>
                                            @endif
                                        @endforelse
                                    </div>
                                </td>
                                <td class="px-gutter-md py-gutter-sm">
                                    @if ($studyEvents->isEmpty())
                                        <span class="badge badge-neutral">Sin consultar</span>
                                    @else
                                        <div class="flex flex-col gap-gutter-xs">
                                            @if ($studyEvents->has('document.viewed'))
                                                <span class="badge badge-info">Visto ×{{ $studyEvents['document.viewed']->total }}</span>
                                            @endif
                                            @if ($studyEvents->has('document.downloaded'))
                                                <span class="badge badge-success">Descargado ×{{ $studyEvents['document.downloaded']->total }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-gutter-md py-gutter-lg text-body-md text-on-surface-variant">
                                    Aún no hay estudios. Se registran al recibir el primer resultado en <a href="{{ route('staff.intake') }}" class="text-primary text-label-lg">Recepción</a>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="xl:col-span-4 card flex flex-col gap-gutter-md">
            <div class="flex items-center justify-between gap-gutter-sm">
                <h2 class="text-title-lg text-on-surface">Actividad reciente</h2>
                <a href="{{ route('staff.audit') }}" class="text-label-lg text-primary">Ver bitácora</a>
            </div>
            <ol class="flex flex-col gap-gutter-sm">
                @forelse ($events as $event)
                    <li class="flex flex-col border-b border-outline-variant/40 pb-gutter-sm">
                        <span class="text-label-lg text-on-surface">{{ \App\Support\Labels::action($event->action) }}</span>
                        <span class="text-label-sm text-on-surface-variant tabular-nums">
                            {{ \App\Support\Labels::dt($event->created_at) }} ·
                            {{ $event->actor?->name ?? ($event->actor_type === 'patient' ? 'Paciente' : 'Sistema') }}
                            @if ($event->study) · {{ $event->study->folio }} @endif
                            @if ($event->result !== 'ok') · {{ $event->result }} @endif
                        </span>
                    </li>
                @empty
                    <li class="text-body-md text-on-surface-variant">Sin actividad registrada.</li>
                @endforelse
            </ol>
        </aside>
    </div>
@endsection
