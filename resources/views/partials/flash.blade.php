@if (session('status'))
    <div role="status" class="p-gutter-md rounded-md bg-success-container text-on-success-container text-body-md flex items-start gap-gutter-sm">
        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">check_circle</span>
        <span>{{ session('status') }}</span>
    </div>
@endif

@if ($errors->any())
    <div role="alert" class="p-gutter-md rounded-md bg-error-container text-on-error-container text-body-md flex items-start gap-gutter-sm">
        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">error</span>
        <ul class="flex flex-col gap-gutter-xs">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
