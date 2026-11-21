@if (\App\Http\Controllers\Auth\LoginController::quickLoginEnabled())
    <div class="flex flex-col gap-gutter-xs {{ $class ?? '' }}">
        <span class="text-label-sm text-on-surface-variant">{{ $title ?? 'Cambio rápido (solo DEMO)' }}</span>
        <div class="grid grid-cols-3 gap-gutter-xs">
            @foreach (['operator' => ['Operadora', 'upload_file'], 'publisher' => ['Publicadora', 'verified_user'], 'admin' => ['Admin', 'manage_accounts']] as $role => [$label, $icon])
                @php $current = auth()->check() && auth()->user()->email === config('eira.demo_accounts')[$role]; @endphp
                <form method="POST" action="{{ route('login.demo', $role) }}">
                    @csrf
                    <button type="submit" @disabled($current) title="Entrar como {{ $label }} DEMO"
                            class="w-full min-h-[40px] px-gutter-xs rounded-full text-label-md border transition-colors duration-150 flex items-center justify-center gap-gutter-xs
                                   {{ $current ? 'bg-primary text-on-primary border-primary cursor-default' : 'bg-surface-container-lowest text-primary border-outline-variant hover:bg-surface-container-low cursor-pointer' }}">
                        <span class="material-symbols-outlined text-[16px]" aria-hidden="true">{{ $icon }}</span>
                        {{ $label }}
                    </button>
                </form>
            @endforeach
        </div>
    </div>
@endif
