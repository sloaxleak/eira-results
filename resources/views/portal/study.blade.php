@extends('layouts.portal')

@section('title', 'Tu documento')

@section('content')
    <section class="flex flex-col gap-gutter-md">
        @forelse ($documents as $document)
            <article class="card flex flex-col gap-gutter-md">
                <div class="flex items-center justify-between gap-gutter-sm">
                    <span class="badge badge-success">
                        <span class="material-symbols-outlined text-[14px]" aria-hidden="true">check_circle</span>
                        Documento disponible
                    </span>
                </div>

                <div>
                    <h2 class="text-title-lg text-on-surface">{{ $document->type === 'prescription' ? 'Documento adjunto de tu médico' : 'Tu documento está listo para consulta' }}</h2>
                    <p class="text-body-md text-on-surface-variant mt-gutter-xs">
                        Folio <span class="text-label-lg text-on-surface tabular-nums">{{ $study->external_form_folio }}</span>
                    </p>
                </div>

                <div class="p-gutter-md rounded-lg bg-surface-container-low border border-outline-variant/50 flex items-center gap-gutter-md">
                    <div class="w-12 h-12 rounded-md bg-surface-container-lowest border border-outline-variant flex items-center justify-center text-primary shrink-0">
                        <span class="material-symbols-outlined text-[26px]" aria-hidden="true">picture_as_pdf</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-label-lg text-on-surface truncate">Documento PDF</p>
                        <p class="text-label-sm text-on-surface-variant">Disponible desde {{ \App\Support\Labels::dt($document->published_at, 'd/m/Y') }}</p>
                    </div>
                </div>

                <div class="flex flex-col gap-gutter-sm">
                    <a href="{{ route('portal.document', [$document->id, 'descargar']) }}" class="btn btn-primary w-full min-h-[48px]">
                        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">download</span>
                        Descargar PDF
                    </a>
                    <a href="{{ route('portal.document', [$document->id, 'ver']) }}" target="_blank" rel="noopener" class="btn btn-secondary w-full">
                        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">visibility</span>
                        Ver documento en pantalla
                    </a>
                </div>
            </article>
        @empty
            <article class="card flex flex-col gap-gutter-md">
                <span class="badge badge-warning self-start">
                    <span class="material-symbols-outlined text-[14px]" aria-hidden="true">schedule</span>
                    No disponible
                </span>
                <p class="text-body-md text-on-surface-variant">Tu documento no está disponible en este momento. Acude a tu unidad EIRA para recibir apoyo.</p>
            </article>
        @endforelse

        <p class="text-label-sm text-on-surface-variant">
            Por tu privacidad, este portal no muestra ni interpreta información médica en pantalla. Para dudas sobre tu estudio, acude a tu unidad EIRA.
            Tu consulta se cierra sola a las {{ \Illuminate\Support\Carbon::createFromTimestamp($expiresAt)->timezone(config('eira.display_timezone'))->format('H:i') }} h.
        </p>

        <form method="POST" action="{{ route('portal.logout') }}">
            @csrf
            <button type="submit" class="btn btn-secondary w-full">Cerrar consulta</button>
        </form>
    </section>
@endsection
