@extends('layouts.admin.guest')

@section('title', 'Reset Password')

@section('content')
    <div class="card card-md">
        <div class="card-body">
            <h2 class="card-title text-center mb-2">
                Reset admin password
            </h2>
            <p class="text-secondary text-center mb-4">
                Choose a secure password for this admin account.
            </p>

            <form
                method="POST"
                action="{{ route('admin.password.update') }}"
            >
                @csrf

                <input
                    type="hidden"
                    name="token"
                    value="{{ $token }}"
                >

                <div class="mb-3">
                    <label class="form-label">
                        Email
                    </label>
                    <input
                        type="email"
                        name="email"
                        value="{{ old('email', $email) }}"
                        class="form-control @error('email') is-invalid @enderror"
                    >
                    @error('email')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">
                        New password
                    </label>
                    <input
                        type="password"
                        name="password"
                        class="form-control @error('password') is-invalid @enderror"
                        autocomplete="new-password"
                    >
                    @error('password')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">
                        Confirm password
                    </label>
                    <input
                        type="password"
                        name="password_confirmation"
                        class="form-control"
                        autocomplete="new-password"
                    >
                </div>

                <button
                    type="submit"
                    class="btn btn-primary w-100"
                >
                    Reset password
                </button>
            </form>
        </div>
    </div>
@endsection
