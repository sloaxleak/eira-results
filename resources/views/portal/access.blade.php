@extends('layouts.portal')

@section('title', 'Consulta tus resultados')

@section('content')
    <section class="flex flex-col gap-gutter-lg">
        <div class="flex flex-col gap-gutter-xs pt-gutter-xs">
            <h1 class="text-display-lg-mobile text-on-surface">Consulta tus resultados</h1>
            <p class="text-body-md text-on-surface-variant">
                Ingresa el No. de folio que aparece en tu formato de solicitud y el código que enviamos por SMS al contacto que registraste en tu unidad EIRA.
            </p>
        </div>

        <form method="POST" action="{{ route('portal.redeem') }}" class="card flex flex-col gap-gutter-md" autocomplete="off">
            @csrf
            <label class="flex flex-col gap-gutter-xs">
                <span class="text-label-md text-on-surface">No. de folio de tu formato</span>
                <input type="text" name="folio" value="{{ old('folio') }}" required maxlength="40" placeholder="Ej. 104512" autocapitalize="characters" class="field uppercase">
                <span class="text-label-sm text-on-surface-variant">Está en la parte superior de tu formato de solicitud.</span>
            </label>

            <label class="flex flex-col gap-gutter-xs">
                <span class="text-label-md text-on-surface">Código recibido por SMS</span>
                <input type="text" name="code" required maxlength="20" placeholder="6 caracteres" inputmode="text" autocapitalize="characters" class="field uppercase tracking-widest">
                <span class="text-label-sm text-on-surface-variant">Es de un solo uso y vence {{ \App\Services\Access\AccessCodes::ttlLabel() }} después de recibirlo.</span>
            </label>

            <button type="submit" class="btn btn-primary w-full min-h-[48px]">
                <span class="material-symbols-outlined text-[20px]" aria-hidden="true">lock_open</span>
                Consultar documento
            </button>
        </form>

        <form method="POST" action="{{ route('portal.resend') }}" class="card flex flex-col gap-gutter-md" autocomplete="off">
            @csrf
            <div>
                <h2 class="text-title-md text-on-surface">¿Tu código venció?</h2>
                <p class="text-body-md text-on-surface-variant">Podemos enviar uno nuevo únicamente al teléfono o correo que ya registraste. No es posible indicar un contacto distinto aquí.</p>
            </div>
            <label class="flex flex-col gap-gutter-xs">
                <span class="text-label-md text-on-surface">No. de folio de tu formato</span>
                <input type="text" name="folio" value="{{ old('folio') }}" required maxlength="40" placeholder="Ej. 104512" autocapitalize="characters" class="field uppercase">
            </label>
            <button type="submit" class="btn btn-secondary w-full">Enviar código nuevo</button>
        </form>
    </section>
@endsection
