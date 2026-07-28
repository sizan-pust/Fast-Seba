@extends('layouts.admin.guest')

@section('title', 'Forgot Password')

@section('content')
    <div class="card card-md">
        <div class="card-body">
            <h2 class="card-title text-center mb-2">
                Forgot password
            </h2>
            <p class="text-secondary text-center mb-4">
                Enter the email associated with your active admin account.
            </p>

            <form
                method="POST"
                action="{{ route('admin.password.email') }}"
            >
                @csrf

                <div class="mb-3">
                    <label class="form-label">
                        Admin email
                    </label>
                    <input
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        class="form-control @error('email') is-invalid @enderror"
                        placeholder="admin@fastsheba.com"
                        autofocus
                    >
                    @error('email')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <div class="form-footer">
                    <button
                        type="submit"
                        class="btn btn-primary w-100"
                    >
                        Send reset link
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="text-center text-secondary mt-3">
        Return to
        <a href="{{ route('admin.login') }}">
            admin login
        </a>
    </div>
@endsection
