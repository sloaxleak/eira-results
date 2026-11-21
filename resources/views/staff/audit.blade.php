@extends('layouts.staff')

@php
    $L = \App\Support\Labels::class;
    $C = \App\Http\Controllers\Staff\AuditController::class;
@endphp

@section('title', 'Bitácora')
@section('heading', 'Bitácora de auditoría')
@section('subheading', 'Inicios de sesión, accesos a documentos, avisos y cambios de estado · sin contenido clínico, códigos ni datos de contacto')

@section('content')
    <section class="card flex flex-col gap-gutter-md" aria-label="Resumen de avisos">
        <div>
            <h2 class="text-title-lg text-on-surface">Avisos por canal</h2>
            <p class="text-label-sm text-on-surface-variant">«Aceptado por transporte» no confirma entrega. Un aviso fallido no afecta la disponibilidad del documento.</p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-gutter-md">
            @foreach (['sms' => 'SMS', 'email' => 'Correo'] as $channel => $label)
                <div class="rounded-lg border border-outline-variant/60 p-gutter-md flex flex-col gap-gutter-sm">
                    <span class="text-label-lg text-on-surface">{{ $label }}</span>
                    <div class="flex flex-wrap gap-gutter-xs">
                        @forelse ($notificationSummary->get($channel, collect()) as $row)
                            <span class="badge {{ $L::notification($row->status)[1] }}">{{ $L::notification($row->status)[0] }} · <span class="tabular-nums">{{ $row->total }}</span></span>
                        @empty
                            <span class="badge badge-neutral">Sin avisos</span>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <form method="GET" action="{{ route('staff.audit') }}" class="card grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-6 gap-gutter-sm items-end">
        <label class="flex flex-col gap-gutter-xs xl:col-span-2">
            <span class="text-label-md text-on-surface">Categoría</span>
            <select name="category" class="field">
                <option value="">Todas</option>
                @foreach ($C::CATEGORIES as $key => [$label])
                    <option value="{{ $key }}" @selected(($filters['category'] ?? null) === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="flex flex-col gap-gutter-xs">
            <span class="text-label-md text-on-surface">Resultado</span>
            <select name="result" class="field">
                <option value="">Todos</option>
                @foreach ($C::RESULTS as $key => [$label])
                    <option value="{{ $key }}" @selected(($filters['result'] ?? null) === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="flex flex-col gap-gutter-xs">
            <span class="text-label-md text-on-surface">Desde</span>
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="field">
        </label>
        <label class="flex flex-col gap-gutter-xs">
            <span class="text-label-md text-on-surface">Hasta</span>
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="field">
        </label>
        <label class="flex flex-col gap-gutter-xs">
            <span class="text-label-md text-on-surface">Folio</span>
            <input type="text" name="folio" value="{{ $filters['folio'] ?? '' }}" maxlength="40" placeholder="Interno o del formato" class="field uppercase">
        </label>
        <div class="flex gap-gutter-sm sm:col-span-2 xl:col-span-6 justify-end">
            <a href="{{ route('staff.audit') }}" class="btn btn-secondary">Limpiar</a>
            <button type="submit" class="btn btn-primary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">filter_list</span>
                Filtrar
            </button>
        </div>
    </form>

    <section class="flex flex-col gap-gutter-md">
        <p class="text-body-md text-on-surface-variant">{{ $events->total() }} evento(s). Horario {{ config('eira.display_timezone') }}.</p>

        <div class="bg-surface-container-lowest rounded-xl border border-outline-variant/60 shadow-sm overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-container-low border-b border-outline-variant/60 text-label-md text-on-surface-variant">
                        <th class="px-gutter-md py-gutter-sm">Fecha</th>
                        <th class="px-gutter-md py-gutter-sm">Actor</th>
                        <th class="px-gutter-md py-gutter-sm">Acción</th>
                        <th class="px-gutter-md py-gutter-sm">Resultado</th>
                        <th class="px-gutter-md py-gutter-sm">Estudio / documento</th>
                        <th class="px-gutter-md py-gutter-sm">Detalle</th>
                        <th class="px-gutter-md py-gutter-sm">IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($events as $event)
                        @php $badge = $L::result($event->result); @endphp
                        <tr class="border-b border-outline-variant/40 text-body-md align-top">
                            <td class="px-gutter-md py-gutter-sm tabular-nums whitespace-nowrap">{{ $L::dt($event->created_at, 'd/m/Y H:i:s') }}</td>
                            <td class="px-gutter-md py-gutter-sm">
                                @if ($event->actor)
                                    <p class="text-label-lg">{{ $event->actor->name }}</p>
                                    <p class="text-label-sm text-on-surface-variant">{{ $event->actor->roleLabel() }}</p>
                                @else
                                    <p class="text-label-lg">{{ ['patient' => 'Paciente', 'staff' => 'Personal'][$event->actor_type] ?? 'Sistema / sin sesión' }}</p>
                                @endif
                            </td>
                            <td class="px-gutter-md py-gutter-sm">
                                <p class="text-label-lg">{{ $L::action($event->action) }}</p>
                                <p class="text-label-sm text-on-surface-variant">{{ $event->action }}</p>
                            </td>
                            <td class="px-gutter-md py-gutter-sm"><span class="badge {{ $badge[1] }}">{{ $badge[0] }}</span></td>
                            <td class="px-gutter-md py-gutter-sm tabular-nums">
                                @if ($event->study)
                                    <p>{{ $event->study->folio }}</p>
                                    <p class="text-label-sm text-on-surface-variant">Formato {{ $event->study->external_form_folio ?? '—' }}</p>
                                @endif
                                @if ($event->document_id)
                                    <a href="{{ route('staff.documents.show', $event->document_id) }}" class="text-label-sm text-primary">Doc. {{ mb_strtoupper(mb_substr($event->document_id, 0, 8)) }}</a>
                                @endif
                                @if (! $event->study && ! $event->document_id) — @endif
                            </td>
                            <td class="px-gutter-md py-gutter-sm text-label-sm text-on-surface-variant">
                                @foreach ($event->meta ?? [] as $key => $value)
                                    <span class="block">{{ $key }}: {{ is_scalar($value) ? $value : json_encode($value) }}</span>
                                @endforeach
                            </td>
                            <td class="px-gutter-md py-gutter-sm text-label-sm tabular-nums text-on-surface-variant">{{ $event->ip ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-gutter-md py-gutter-lg text-body-md text-on-surface-variant">No hay eventos con estos filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $events->links() }}
    </section>
@endsection
