@extends('layouts.staff')

@section('title', 'Publicación')
@section('heading', 'Publicación de Documentos')
@section('subheading', 'Decisión de publicar de EIRA sobre documentos ya revisados · no es una nueva interpretación médica')

@section('content')
    @cannot('documents.publish')
        <div class="p-gutter-md rounded-md bg-info-container text-on-info-container text-body-md">
            Tu cuenta puede consultar esta cola, pero la publicación requiere una cuenta con facultad de publicación. El servidor rechaza el intento aunque se use la ruta directa.
        </div>
    @endcannot

    <section class="flex flex-col gap-gutter-md">
        <h2 class="text-title-lg text-on-surface">Listos para publicación</h2>
        @include('staff.partials.document-list', ['items' => $ready, 'empty' => 'No hay documentos revisados en espera.'])
    </section>

    <section class="flex flex-col gap-gutter-md">
        <h2 class="text-title-lg text-on-surface">Publicados recientes</h2>
        @include('staff.partials.document-list', ['items' => $published, 'empty' => 'Aún no hay documentos publicados.'])
    </section>
@endsection
