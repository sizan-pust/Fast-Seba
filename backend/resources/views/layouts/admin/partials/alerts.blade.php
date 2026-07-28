@if (session('success'))
    <div
        class="alert alert-success alert-dismissible"
        role="alert"
    >
        <div>
            <strong>Success.</strong>
            {{ session('success') }}
        </div>
        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="alert"
            aria-label="Close"
        ></button>
    </div>
@endif

@if (session('status'))
    <div
        class="alert alert-info alert-dismissible"
        role="alert"
    >
        {{ session('status') }}
        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="alert"
            aria-label="Close"
        ></button>
    </div>
@endif

@if ($errors->any())
    <div
        class="alert alert-danger alert-dismissible"
        role="alert"
    >
        <strong>Please review the highlighted fields.</strong>
        <ul class="mb-0 mt-2">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="alert"
            aria-label="Close"
        ></button>
    </div>
@endif
