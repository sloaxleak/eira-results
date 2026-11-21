@extends('layouts.staff')

@php
    $S = \App\Models\Document::class;
    $L = \App\Support\Labels::class;
@endphp

@section('title', 'Documento '.$document->shortId())
@section('heading', $document->typeLabel().' · '.$document->shortId())
@section('subheading', ($document->study ? 'Folio interno '.$document->study->folio : 'Sin asociar a un estudio').' · versión '.$document->version)

@section('content')
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-gutter-lg items-start">
        <section class="xl:col-span-7 bg-surface-container-lowest rounded-xl border border-outline-variant/60 shadow-sm overflow-hidden flex flex-col">
            <div class="px-gutter-lg py-gutter-sm bg-surface-container-low border-b border-outline-variant/60 flex flex-wrap items-center justify-between gap-gutter-sm">
                <div class="flex items-center gap-gutter-sm">
                    <span class="material-symbols-outlined text-[20px] text-primary" aria-hidden="true">{{ $document->isImage() ? 'image' : 'picture_as_pdf' }}</span>
                    <span class="text-label-lg text-on-surface">Vista previa privada</span>
                    <span class="text-label-sm text-on-surface-variant">· {{ $document->original_mime }} · {{ number_format($document->original_size / 1024, 0) }} KB</span>
                </div>
                @include('partials.badge', ['badge' => $L::document($document->status)])
            </div>
            <div class="bg-surface-container p-gutter-md min-h-[640px] flex justify-center">
                @can('documents.view-file')
                    @if ($document->isImage() && ! $document->delivery_path)
                        <img src="{{ route('staff.documents.file', $document) }}" alt="Documento cargado" class="max-w-full h-auto rounded-md shadow-md self-start">
                    @else
                        <iframe src="{{ route('staff.documents.file', [$document, $document->delivery_path ? 'entrega' : 'original']) }}" title="Documento" class="w-full min-h-[640px] rounded-md bg-surface-container-lowest"></iframe>
                    @endif
                @else
                    <p class="text-body-md text-on-surface-variant self-center">Tu cuenta no tiene acceso al contenido del documento.</p>
                @endcan
            </div>
        </section>

        <aside class="xl:col-span-5 flex flex-col gap-gutter-lg">
            {{-- Referencias e integridad --}}
            <article class="card flex flex-col gap-gutter-md">
                <h2 class="text-title-lg text-on-surface">Referencias e integridad</h2>
                <dl class="grid grid-cols-2 gap-gutter-sm text-body-md">
                    <div><dt class="text-label-sm text-on-surface-variant">Folio interno</dt><dd class="tabular-nums">{{ $document->study?->folio ?? '—' }}</dd></div>
                    <div><dt class="text-label-sm text-on-surface-variant">Folio del formato</dt><dd class="tabular-nums">{{ $document->study?->external_form_folio ?? '—' }}</dd></div>
                    <div><dt class="text-label-sm text-on-surface-variant">ID EIRA (referencia, no autentica)</dt><dd class="tabular-nums">{{ $document->study?->patient?->external_eira_id ?? '—' }}</dd></div>
                    <div><dt class="text-label-sm text-on-surface-variant">Número citológico</dt><dd class="tabular-nums">{{ $document->study?->external_cytology_number ?? '—' }}</dd></div>
                    <div><dt class="text-label-sm text-on-surface-variant">Fecha de toma</dt><dd class="tabular-nums">{{ $document->study?->taken_at?->format('d/m/Y') ?? '—' }}</dd></div>
                    <div class="col-span-2"><dt class="text-label-sm text-on-surface-variant">SHA-256 del original (integridad, no cifrado)</dt><dd class="tabular-nums break-all text-label-sm">{{ $document->sha256 }}</dd></div>
                    <div><dt class="text-label-sm text-on-surface-variant">Cargado por</dt><dd>{{ $document->uploader?->name }} · {{ $L::dt($document->created_at) }}</dd></div>
                    <div><dt class="text-label-sm text-on-surface-variant">Asociado por</dt><dd>{{ $document->associator?->name ?? '—' }} @if ($document->associated_at) · {{ $L::dt($document->associated_at) }} @endif</dd></div>
                    <div><dt class="text-label-sm text-on-surface-variant">Revisado por</dt><dd>{{ $document->reviewer?->name ?? '—' }} @if ($document->reviewed_at) · {{ $L::dt($document->reviewed_at) }} @endif</dd></div>
                    <div><dt class="text-label-sm text-on-surface-variant">Publicado por</dt><dd>{{ $document->publisher?->name ?? '—' }} @if ($document->published_at) · {{ $L::dt($document->published_at) }} @endif</dd></div>
                </dl>
            </article>

            {{-- Documentos del estudio --}}
            @if ($siblings->isNotEmpty())
                <article class="card flex flex-col gap-gutter-sm">
                    <h2 class="text-title-lg text-on-surface">Otros documentos del estudio</h2>
                    @foreach ($siblings as $sibling)
                        <a href="{{ route('staff.documents.show', $sibling) }}" class="flex flex-wrap items-center justify-between gap-gutter-sm border-b border-outline-variant/40 pb-gutter-sm">
                            <span class="text-label-lg text-primary">{{ $sibling->typeLabel() }} v{{ $sibling->version }} · {{ $sibling->shortId() }}</span>
                            @include('partials.badge', ['badge' => $L::document($sibling->status)])
                        </a>
                    @endforeach
                </article>
            @endif

            {{-- Contactos --}}
            @if ($document->study)
                <article class="card flex flex-col gap-gutter-sm">
                    <h2 class="text-title-lg text-on-surface">Contactos para aviso</h2>
                    @forelse ($document->study->contacts as $contact)
                        <div class="flex flex-wrap items-center justify-between gap-gutter-sm border-b border-outline-variant/40 pb-gutter-sm">
                            <span class="text-label-lg"><span class="uppercase">{{ $contact->channel }}</span> · <span class="text-on-surface-variant">{{ $contact->masked() }}</span></span>
                            @if ($contact->verified_at)
                                <span class="badge badge-success">Confirmado · {{ $L::dt($contact->verified_at) }}</span>
                            @else
                                <span class="flex items-center gap-gutter-sm">
                                    <span class="badge badge-warning">Sin verificar</span>
                                    @can('documents.intake')
                                        <form method="POST" action="{{ route('staff.contacts.verify', $contact) }}">
                                            @csrf
                                            <button type="submit" class="text-label-lg text-primary">Confirmar contacto</button>
                                        </form>
                                    @endcan
                                </span>
                            @endif
                        </div>
                    @empty
                        <p class="text-body-md text-on-surface-variant">Sin contacto registrado: la entrega será por atención asistida.</p>
                    @endforelse
                    <p class="text-label-sm text-on-surface-variant">Supuesto del demo: confirmar significa que el personal validó el contacto con la paciente. El procedimiento real está pendiente de definir con EIRA.</p>
                </article>
            @endif

            {{-- Asociación --}}
            @if ($document->status === $S::STATUS_PENDING_ASSOCIATION)
                <article class="card flex flex-col gap-gutter-md">
                    <h2 class="text-title-lg text-on-surface">1. Asociar a un estudio</h2>
                    <p class="text-body-md text-on-surface-variant">Busca por folio interno, folio del formulario o número citológico (coincidencia exacta). No se asocia por nombre ni por ID EIRA; si hay duda, deja el documento pendiente.</p>
                    @can('documents.intake')
                        <form method="GET" action="{{ route('staff.documents.show', $document) }}" class="flex gap-gutter-sm">
                            <input type="text" name="folio" value="{{ $query }}" required placeholder="Ej. EIRA-DEMO-2026-0001" class="field" aria-label="Folio o referencia administrativa">
                            <button type="submit" class="btn btn-secondary shrink-0">Buscar</button>
                        </form>

                        @if ($query !== '' && $candidates->isEmpty())
                            <p class="text-body-md text-on-error-container bg-error-container rounded-md p-gutter-sm">Sin coincidencia exacta. El documento permanece pendiente.</p>
                        @endif
                        @if ($candidates->count() > 1)
                            <p class="text-body-md text-on-warning-container bg-warning-container rounded-md p-gutter-sm">Hay más de una coincidencia: la referencia es ambigua. Verifica contra el folio interno antes de confirmar.</p>
                        @endif

                        @foreach ($candidates as $candidate)
                            <form method="POST" action="{{ route('staff.documents.associate', $document) }}" class="rounded-lg border border-outline-variant p-gutter-md flex flex-col gap-gutter-sm">
                                @csrf
                                <input type="hidden" name="study_id" value="{{ $candidate->id }}">
                                <dl class="grid grid-cols-2 gap-gutter-sm text-body-md">
                                    <div><dt class="text-label-sm text-on-surface-variant">Folio interno</dt><dd class="text-label-lg tabular-nums">{{ $candidate->folio }}</dd></div>
                                    <div><dt class="text-label-sm text-on-surface-variant">Fecha de toma</dt><dd class="tabular-nums">{{ $candidate->taken_at?->format('d/m/Y') ?? '—' }}</dd></div>
                                    <div><dt class="text-label-sm text-on-surface-variant">Folio del formulario</dt><dd class="tabular-nums">{{ $candidate->external_form_folio ?? '—' }}</dd></div>
                                    <div><dt class="text-label-sm text-on-surface-variant">Número citológico</dt><dd class="tabular-nums">{{ $candidate->external_cytology_number ?? '—' }}</dd></div>
                                </dl>
                                @if ($candidate->active_siblings > 0)
                                    <label class="flex items-start gap-gutter-sm p-gutter-sm rounded-md bg-warning-container text-on-warning-container text-body-md">
                                        <input type="checkbox" name="confirm_new_version" value="1" class="mt-1">
                                        <span><strong>Posible duplicado:</strong> este estudio ya tiene {{ $candidate->active_siblings }} documento(s) activo(s) de este tipo. Marca solo si es una corrección; se creará una nueva versión y la anterior no se sobrescribe.</span>
                                    </label>
                                @endif
                                <label class="flex items-start gap-gutter-sm text-body-md">
                                    <input type="checkbox" name="confirm" value="1" required class="mt-1">
                                    <span>Verifiqué que las referencias del documento corresponden a este estudio.</span>
                                </label>
                                <button type="submit" class="btn btn-primary">Confirmar asociación</button>
                            </form>
                        @endforeach
                    @else
                        <p class="text-body-md text-on-surface-variant">La asociación corresponde a operación administrativa.</p>
                    @endcan
                </article>
            @endif

            {{-- Revisión --}}
            @if ($document->status === $S::STATUS_PENDING_REVIEW)
                <article class="card flex flex-col gap-gutter-md">
                    <h2 class="text-title-lg text-on-surface">2. Revisión administrativa</h2>
                    @can('documents.intake')
                        <form method="POST" action="{{ route('staff.documents.review', $document) }}" class="flex flex-col gap-gutter-sm">
                            @csrf
                            @foreach ([
                                'check_legible' => 'El documento es legible y está completo.',
                                'check_matches_study' => 'Las referencias del documento corresponden al estudio asociado.',
                                'check_origin_authorization' => 'El documento muestra evidencia de autorización clínica de origen (laboratorio).',
                            ] as $name => $label)
                                <label class="flex items-start gap-gutter-sm text-body-md">
                                    <input type="checkbox" name="{{ $name }}" value="1" required class="mt-1">
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                            @if ($document->isImage())
                                <p class="text-label-sm text-on-surface-variant">Al confirmar, la imagen se convierte a PDF para entrega sin modificar su contenido; el original se conserva.</p>
                            @endif
                            @if (config('eira.publish_on_review'))
                                @php $pendingContacts = $document->study?->contacts->whereNull('verified_at') ?? collect(); @endphp
                                @if ($pendingContacts->isNotEmpty())
                                    <label class="flex items-start gap-gutter-sm text-body-md p-gutter-sm rounded-md bg-warning-container text-on-warning-container">
                                        <input type="checkbox" name="confirm_contacts" value="1" checked class="mt-1">
                                        <span>Confirmo que {{ $pendingContacts->map->masked()->implode(' y ') }} pertenece(n) a la paciente y puede(n) recibir el aviso.</span>
                                    </label>
                                @endif
                                <p class="text-label-sm text-on-surface-variant">Al confirmar, el documento se <strong>publica</strong> y se envía el aviso con el código a los contactos confirmados.</p>
                                <button type="submit" class="btn btn-primary">
                                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">send</span>
                                    Confirmar, publicar y notificar
                                </button>
                            @else
                                <button type="submit" class="btn btn-primary">Confirmar revisión</button>
                            @endif
                        </form>
                    @else
                        <p class="text-body-md text-on-surface-variant">La revisión corresponde a operación administrativa.</p>
                    @endcan
                </article>
            @endif

            {{-- Publicación --}}
            @if ($document->status === $S::STATUS_READY)
                <article class="card flex flex-col gap-gutter-md">
                    <h2 class="text-title-lg text-on-surface">3. Decisión de publicar</h2>
                    <ul class="flex flex-col gap-gutter-xs text-body-md">
                        @foreach (['Legibilidad confirmada', 'Correspondencia con el estudio confirmada', 'Evidencia de autorización de origen confirmada'] as $evidence)
                            <li class="flex items-center gap-gutter-sm"><span class="material-symbols-outlined text-[18px] text-success" aria-hidden="true">check_circle</span>{{ $evidence }}</li>
                        @endforeach
                    </ul>
                    <p class="text-label-sm text-on-surface-variant">Supuesto del demo: quien publica es una cuenta distinta de quien revisa. La facultad de publicación está pendiente de confirmar con EIRA.</p>
                    @can('documents.publish')
                        <form method="POST" action="{{ route('staff.documents.publish', $document) }}" class="flex flex-col gap-gutter-sm">
                            @csrf
                            <label class="flex items-start gap-gutter-sm text-body-md">
                                <input type="checkbox" name="confirm" value="1" required class="mt-1">
                                <span>Autorizo la publicación de este documento para consulta de la paciente.</span>
                            </label>
                            <button type="submit" class="btn btn-primary">
                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">verified_user</span>
                                Autorizar publicación
                            </button>
                        </form>
                    @else
                        <div class="p-gutter-md rounded-md bg-info-container text-on-info-container text-body-md flex flex-col gap-gutter-sm">
                            <p class="flex items-start gap-gutter-sm">
                                <span class="material-symbols-outlined text-[20px]" aria-hidden="true">block</span>
                                <span><strong>Tu cuenta ({{ auth()->user()->roleLabel() }}) no puede publicar.</strong> La revisión ya quedó registrada; la publicación la decide una cuenta distinta con facultad de publicación, para que una sola persona no revise y libere el mismo resultado.</span>
                            </p>
                            <p class="text-label-sm">El servidor lo exige: un intento de publicar desde esta cuenta se rechaza (403) y queda en la bitácora.</p>
                            @if (\App\Http\Controllers\Auth\LoginController::quickLoginEnabled())
                                <form method="POST" action="{{ route('login.demo', 'publisher') }}">
                                    @csrf
                                    <input type="hidden" name="redirect" value="{{ route('staff.documents.show', $document) }}">
                                    <button type="submit" class="btn btn-secondary">
                                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">swap_horiz</span>
                                        Cambiar a Publicadora DEMO para publicar
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endcan
                </article>
            @endif

            {{-- Rechazo --}}
            @php
                $canReject = ($document->status === $S::STATUS_READY && auth()->user()->can('documents.publish'))
                    || (in_array($document->status, [$S::STATUS_PENDING_ASSOCIATION, $S::STATUS_PENDING_REVIEW], true) && auth()->user()->can('documents.intake'));
            @endphp
            @if ($canReject)
                <form method="POST" action="{{ route('staff.documents.reject', $document) }}" class="card flex flex-col gap-gutter-sm">
                    @csrf
                    <h2 class="text-title-md text-on-surface">Rechazar documento</h2>
                    <select name="reason" required class="field" aria-label="Motivo de rechazo">
                        @foreach ($S::REJECTION_REASONS as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-danger">Rechazar</button>
                </form>
            @endif

            {{-- Publicado: avisos y revocación --}}
            @if (in_array($document->status, [$S::STATUS_PUBLISHED, $S::STATUS_REVOKED], true))
                <article class="card flex flex-col gap-gutter-md">
                    <h2 class="text-title-lg text-on-surface">Avisos a la paciente</h2>
                    <p class="text-label-sm text-on-surface-variant">Los avisos no afectan la disponibilidad del documento. «Aceptado por transporte» no confirma entrega.</p>
                    @forelse ($document->notifications as $notification)
                        <div class="flex flex-wrap items-center justify-between gap-gutter-sm border-b border-outline-variant/40 pb-gutter-sm">
                            <div class="flex flex-col gap-gutter-xs">
                                <span class="text-label-lg uppercase">{{ $notification->channel }} · <span class="normal-case text-on-surface-variant">{{ $notification->contact?->masked() ?? 'contacto eliminado' }}</span></span>
                                <span class="text-label-sm text-on-surface-variant tabular-nums">Intentos: {{ $notification->attempts }} · {{ $L::dt($notification->last_attempt_at) }} @if ($notification->last_error) · {{ $notification->last_error }} @endif</span>
                            </div>
                            <div class="flex items-center gap-gutter-sm">
                                @include('partials.badge', ['badge' => $L::notification($notification->status)])
                                @if ($notification->status === 'failed' && $document->status === $S::STATUS_PUBLISHED)
                                    <form method="POST" action="{{ route('staff.notifications.retry', $notification) }}">
                                        @csrf
                                        <button type="submit" class="text-label-lg text-primary">Reintentar</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-body-md text-on-surface-variant">Sin contacto verificado para este estudio: no se envió aviso. Corresponde atención asistida con verificación humana.</p>
                    @endforelse
                </article>

                @if ($document->status === $S::STATUS_PUBLISHED)
                    @can('documents.publish')
                        <form method="POST" action="{{ route('staff.documents.revoke', $document) }}" class="card flex flex-col gap-gutter-sm">
                            @csrf
                            <h2 class="text-title-md text-on-surface">Revocar publicación</h2>
                            <p class="text-label-sm text-on-surface-variant">El documento deja de poder consultarse en la siguiente solicitud, incluso con una sesión abierta.</p>
                            <select name="reason" required class="field" aria-label="Motivo de revocación">
                                @foreach ($S::REVOCATION_REASONS as $value => $label)
                                    @continue($value === 'superseded')
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-danger">Revocar</button>
                        </form>
                    @endcan
                @endif
            @endif

            @if ($document->status === $S::STATUS_REJECTED)
                <div class="card text-body-md">Rechazado: {{ $S::REJECTION_REASONS[$document->rejection_reason] ?? $document->rejection_reason }} · {{ $L::dt($document->rejected_at) }}</div>
            @endif
            @if ($document->status === $S::STATUS_REVOKED)
                <div class="card text-body-md">Revocado: {{ $S::REVOCATION_REASONS[$document->revocation_reason] ?? $document->revocation_reason }} · {{ $L::dt($document->revoked_at) }}</div>
            @endif

            {{-- Bitácora --}}
            <article class="card flex flex-col gap-gutter-md">
                <h2 class="text-title-lg text-on-surface">Bitácora del documento</h2>
                <ol class="flex flex-col gap-gutter-sm">
                    @foreach ($events as $event)
                        <li class="flex flex-col border-b border-outline-variant/40 pb-gutter-sm">
                            <span class="text-label-lg text-on-surface">{{ $L::action($event->action) }}</span>
                            <span class="text-label-sm text-on-surface-variant tabular-nums">{{ $L::dt($event->created_at) }} · {{ $event->actor?->name ?? ($event->actor_type === 'patient' ? 'Paciente' : 'Sistema') }} @if ($event->result !== 'ok') · {{ $event->result }} @endif</span>
                        </li>
                    @endforeach
                </ol>
            </article>
        </aside>
    </div>
@endsection
