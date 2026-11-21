@if ($items->isEmpty())
    <div class="card text-body-md text-on-surface-variant">{{ $empty }}</div>
@else
    <div class="bg-surface-container-lowest rounded-xl border border-outline-variant/60 shadow-sm overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-surface-container-low border-b border-outline-variant/60 text-label-md text-on-surface-variant">
                    <th class="px-gutter-md py-gutter-sm">Documento</th>
                    <th class="px-gutter-md py-gutter-sm">Estudio</th>
                    <th class="px-gutter-md py-gutter-sm">Estado</th>
                    <th class="px-gutter-md py-gutter-sm"><span class="sr-only">Acción</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $item)
                    <tr class="border-b border-outline-variant/40 text-body-md">
                        <td class="px-gutter-md py-gutter-sm">
                            <p class="text-label-lg tabular-nums">{{ $item->typeLabel() }} · {{ $item->shortId() }}</p>
                            <p class="text-label-sm text-on-surface-variant tabular-nums">v{{ $item->version }} · {{ \App\Support\Labels::dt($item->created_at) }}</p>
                        </td>
                        <td class="px-gutter-md py-gutter-sm tabular-nums">{{ $item->study?->folio ?? 'Sin asociar' }}</td>
                        <td class="px-gutter-md py-gutter-sm">@include('partials.badge', ['badge' => \App\Support\Labels::document($item->status)])</td>
                        <td class="px-gutter-md py-gutter-sm text-right">
                            <a href="{{ route('staff.documents.show', $item) }}" class="text-label-lg text-primary">Abrir</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
