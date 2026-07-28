@extends('layouts.admin.guest')

@section('title', 'Admin Login')

@section('content')
    <div class="card card-md">
        <div class="card-body">
            <h2 class="h2 text-center mb-2">
                Login to your account
            </h2>
            <p class="text-secondary text-center mb-4">
                Manage the complete FastSheba marketplace.
            </p>

            <form
                method="POST"
                action="{{ route('admin.login.store') }}"
                autocomplete="off"
                novalidate
            >
                @csrf

                <div class="mb-3">
                    <label class="form-label">
                        Email address
                    </label>
                    <input
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        class="form-control @error('email') is-invalid @enderror"
                        placeholder="admin@fastsheba.com"
                        autocomplete="username"
                        autofocus
                    >
                    @error('email')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <div class="mb-2">
                    <label class="form-label">
                        Password
                        <span class="form-label-description">
                            <a href="{{ route('admin.password.request') }}">
                                I forgot password
                            </a>
                        </span>
                    </label>

                    <div class="input-group input-group-flat">
                        <input
                            type="password"
                            name="password"
                            id="admin-password"
                            class="form-control @error('password') is-invalid @enderror"
                            placeholder="Your password"
                            autocomplete="current-password"
                        >

                        <button
                            type="button"
                            class="input-group-text border-0"
                            data-password-toggle="#admin-password"
                        >
                            Show
                        </button>
                    </div>

                    @error('password')
                        <div class="text-danger small mt-1">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <div class="mb-2">
                    <label class="form-check">
                        <input
                            type="checkbox"
                            name="remember"
                            value="1"
                            class="form-check-input"
                        >
                        <span class="form-check-label">
                            Remember me on this device
                        </span>
                    </label>
                </div>

                <div class="form-footer">
                    <button
                        type="submit"
                        class="btn btn-primary w-100"
                    >
                        Sign in
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="text-center text-secondary mt-3">
        Protected FastSheba administration area
    </div>
@endsection
