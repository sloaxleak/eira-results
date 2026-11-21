@if (file_exists(public_path('img/eira-logo.png')))
    <img src="{{ asset('img/eira-logo.png') }}" alt="EIRA" class="{{ $class ?? 'h-8 w-auto' }} object-contain">
@else
    <span class="text-title-lg text-primary tracking-wide" aria-label="EIRA">EIRA</span>
@endif
