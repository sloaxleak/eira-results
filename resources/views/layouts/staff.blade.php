@extends('layouts.base')

@php
    $pendingIntake = \App\Models\Document::whereIn('status', ['pending_association', 'pending_review'])->count();
    $pendingPublication = \App\Models\Document::where('status', 'ready_for_publication')->count();
    $nav = [
        ['staff.dashboard', 'dashboard', 'Panel general', null],
        ['staff.intake', 'upload_file', 'Recepción', $pendingIntake],
        ['staff.publication', 'verified_user', 'Publicación', $pendingPublication],
        ['staff.audit', 'history', 'Bitácora', null],
    ];
    if (auth()->user()->can('users.manage')) {
        $nav[] = ['staff.users.index', 'manage_accounts', 'Usuarios', null];
    }
    if (app(\App\Services\Notifications\DemoOutbox::class)->enabled()) {
        $nav[] = ['staff.outbox', 'science', 'Bandeja simulada', null];
    }
@endphp

@section('body')
    <div class="min-h-screen flex flex-col lg:flex-row">
        <div data-sidebar-backdrop data-sidebar-toggle class="hidden fixed inset-0 z-30 bg-on-surface/30 lg:hidden"></div>

        <aside data-sidebar class="-translate-x-full lg:translate-x-0 fixed lg:static inset-y-0 left-0 z-40 w-64 min-h-screen bg-surface-container-lowest border-r border-outline-variant/60 flex flex-col justify-between p-gutter-lg transition-transform duration-200 shrink-0">
            <div class="flex flex-col gap-gutter-xl">
                <div class="flex items-center justify-between gap-gutter-sm px-gutter-xs">
                    <div class="flex items-center gap-gutter-sm">
                        @include('partials.logo')
                    </div>
                    <button type="button" data-sidebar-toggle aria-label="Cerrar menú" class="lg:hidden inline-flex items-center justify-center w-9 h-9 rounded-full text-on-surface-variant hover:bg-surface-container-low">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <nav aria-label="Navegación principal" class="flex flex-col gap-gutter-xs">
                    @foreach ($nav as [$route, $icon, $label, $count])
                        <a href="{{ route($route) }}" @if (request()->routeIs($route)) aria-current="page" @endif
                           class="nav-link {{ request()->routeIs($route) ? 'nav-link-active' : '' }}">
                            <span class="flex items-center gap-gutter-sm">
                                <span class="material-symbols-outlined text-[20px]" aria-hidden="true">{{ $icon }}</span>
                                <span>{{ $label }}</span>
                            </span>
                            @if ($count)
                                <span class="inline-flex items-center justify-center min-w-6 h-6 px-gutter-xs rounded-full bg-brand-pink-container text-on-brand-pink-container text-label-sm tabular-nums">{{ $count }}</span>
                            @endif
                        </a>
                    @endforeach
                    <a href="{{ route('portal.show') }}" target="_blank" rel="noopener" class="nav-link">
                        <span class="flex items-center gap-gutter-sm">
                            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">smartphone</span>
                            <span>Portal pacientes</span>
                        </span>
                    </a>
                </nav>
            </div>

            <div class="pt-gutter-md border-t border-outline-variant/60 flex flex-col gap-gutter-sm">
                @include('partials.demo-switch', ['class' => 'pb-gutter-sm'])
                <div class="flex items-center gap-gutter-sm">
                    <div class="w-10 h-10 rounded-full bg-surface-container-high text-primary flex items-center justify-center text-label-lg shrink-0">{{ auth()->user()->initials() }}</div>
                    <div class="min-w-0">
                        <p class="text-label-lg text-on-surface truncate">{{ auth()->user()->name }}</p>
                        <p class="text-label-sm text-on-surface-variant truncate">{{ auth()->user()->roleLabel() }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-secondary w-full">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">logout</span>
                        Cerrar sesión
                    </button>
                </form>
            </div>
        </aside>

        <div class="flex-1 flex flex-col min-w-0">
            <header class="bg-surface-container-lowest border-b border-outline-variant/60 px-gutter-lg py-gutter-md flex flex-wrap items-center justify-between gap-gutter-md">
                <div class="flex items-center gap-gutter-sm">
                    <button type="button" data-sidebar-toggle aria-label="Abrir menú" class="lg:hidden inline-flex items-center justify-center w-11 h-11 rounded-full border border-outline-variant text-on-surface hover:bg-surface-container-low">
                        <span class="material-symbols-outlined text-[22px]">menu</span>
                    </button>
                    <div>
                        <h1 class="text-headline-md text-on-surface">@yield('heading')</h1>
                        <p class="text-body-md text-on-surface-variant">@yield('subheading')</p>
                    </div>
                </div>
            </header>

            <main class="flex-1 p-gutter-lg lg:p-gutter-xl flex flex-col gap-gutter-lg max-w-7xl w-full mx-auto">
                @include('partials.flash')
                @yield('content')
            </main>
        </div>
    </div>
@endsection
