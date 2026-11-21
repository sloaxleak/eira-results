@extends('layouts.staff')

@section('title', 'Recepción')
@section('heading', 'Recepción de Documentos')
@section('subheading', 'Carga privada de PDF, JPG o PNG · asociación manual · revisión administrativa')

@section('content')
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-gutter-lg items-start">
        <section class="xl:col-span-5">
            @can('documents.intake')
                <form method="POST" action="{{ route('staff.documents.store') }}" enctype="multipart/form-data"
                      class="rounded-xl p-gutter-lg border-2 border-dashed border-brand-coral bg-surface-container-low/40 flex flex-col items-center text-center gap-gutter-md">
                    @csrf
                    <div class="w-14 h-14 rounded-full bg-brand-pink-container text-on-brand-pink-container flex items-center justify-center">
                        <span class="material-symbols-outlined text-[28px]" aria-hidden="true">cloud_upload</span>
                    </div>
                    <div class="flex flex-col gap-gutter-xs">
                        <h2 class="text-title-lg text-on-surface">Recibir resultado y receta</h2>
                        <p class="text-body-md text-on-surface-variant">PDF, JPG o PNG · máximo {{ intdiv(config('eira.upload.max_kb'), 1024) }} MB. El original se guarda sin cambios en almacenamiento privado.</p>
                    </div>
                    <div class="w-full grid grid-cols-1 sm:grid-cols-2 gap-gutter-sm text-left">
                        @foreach ([
                            'file' => ['Resultado *', 'description', true, 'Obligatorio'],
                            'prescription' => ['Receta', 'prescriptions', false, 'Opcional · solo si el médico la adjuntó'],
                        ] as $name => [$label, $icon, $required, $hint])
                            <div class="rounded-lg bg-surface-container-lowest border border-outline-variant/60 p-gutter-md flex flex-col gap-gutter-sm">
                                <span class="text-label-lg text-on-surface flex items-center gap-gutter-xs">
                                    <span class="material-symbols-outlined text-[18px] text-primary" aria-hidden="true">{{ $icon }}</span>
                                    {{ $label }}
                                </span>
                                <label class="btn btn-secondary w-full">
                                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">attach_file</span>
                                    Elegir archivo
                                    <input type="file" name="{{ $name }}" @required($required) accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" class="sr-only" data-file-input="{{ $name }}">
                                </label>
                                <p class="text-label-sm text-on-surface-variant truncate" data-file-name="{{ $name }}">{{ $hint }}</p>
                            </div>
                        @endforeach
                    </div>
                    <p class="text-label-sm text-on-surface-variant w-full text-left">La receta no sustituye al resultado: se revisa y publica por separado.</p>

                    <fieldset class="w-full text-left flex flex-col gap-gutter-sm border-t border-outline-variant/60 pt-gutter-md">
                        <legend class="text-title-md text-on-surface">Datos del formato</legend>
                        <p class="text-label-sm text-on-surface-variant">Transcribe tal como aparecen en el formato. Si el folio ya existe, el documento no se asocia solo.</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-gutter-sm">
                            <label class="flex flex-col gap-gutter-xs">
                                <span class="text-label-md text-on-surface">No. de folio del formato *</span>
                                <input type="text" name="form_folio" value="{{ old('form_folio') }}" required maxlength="40" autocapitalize="characters" class="field uppercase">
                            </label>
                            <label class="flex flex-col gap-gutter-xs">
                                <span class="text-label-md text-on-surface">ID EIRA *</span>
                                <input type="text" name="eira_id" value="{{ old('eira_id') }}" required maxlength="10" placeholder="Ej. JACA990214" autocapitalize="characters" class="field uppercase">
                            </label>
                            <label class="flex flex-col gap-gutter-xs">
                                <span class="text-label-md text-on-surface">Número citológico</span>
                                <input type="text" name="cytology_number" value="{{ old('cytology_number') }}" maxlength="40" class="field">
                            </label>
                            <label class="flex flex-col gap-gutter-xs">
                                <span class="text-label-md text-on-surface">Fecha de toma</span>
                                <input type="date" name="taken_at" value="{{ old('taken_at') }}" max="{{ now()->toDateString() }}" class="field">
                            </label>
                            <label class="flex flex-col gap-gutter-xs">
                                <span class="text-label-md text-on-surface">Teléfono</span>
                                <input type="tel" name="phone" value="{{ old('phone') }}" maxlength="20" inputmode="tel" placeholder="10 dígitos" class="field">
                            </label>
                            <label class="flex flex-col gap-gutter-xs">
                                <span class="text-label-md text-on-surface">Correo</span>
                                <input type="email" name="email" value="{{ old('email') }}" maxlength="120" class="field">
                            </label>
                        </div>
                        <p class="text-label-sm text-on-surface-variant">El teléfono y el correo quedan <strong>sin verificar</strong>; no reciben código hasta que el personal los confirme.</p>
                        <label class="flex items-start gap-gutter-sm text-body-md">
                            <input type="checkbox" name="confirm" value="1" required class="mt-1">
                            <span>Verifiqué que los datos capturados corresponden al formato del archivo.</span>
                        </label>
                    </fieldset>

                    <button type="submit" class="btn btn-primary w-full">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">upload_file</span>
                        Registrar recepción
                    </button>
                </form>
            @else
                <div class="card text-body-md text-on-surface-variant">Tu cuenta no tiene permiso de carga. La recepción corresponde a operación administrativa.</div>
            @endcan
        </section>

        <section class="xl:col-span-7 flex flex-col gap-gutter-md">
            <h2 class="text-title-lg text-on-surface">Pendientes de asociación o revisión</h2>
            @include('staff.partials.document-list', ['items' => $documents, 'empty' => 'No hay documentos pendientes.'])

            @if ($closed->isNotEmpty())
                <h2 class="text-title-lg text-on-surface pt-gutter-md">Rechazados o revocados recientes</h2>
                @include('staff.partials.document-list', ['items' => $closed, 'empty' => ''])
            @endif
        </section>
    </div>
@endsection
