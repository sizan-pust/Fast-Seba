@extends('layouts.admin.app')

@section('title', 'Admin Profile')

@section('content')
    <div class="page-header d-print-none">
        <div class="row align-items-center">
            <div class="col">
                <div class="page-pretitle">
                    Account
                </div>
                <h2 class="page-title">
                    Admin profile
                </h2>
            </div>
        </div>
    </div>

    <div class="row row-cards">
        <div class="col-lg-7">
            <form
                method="POST"
                action="{{ route('admin.profile.update') }}"
                class="card"
            >
                @csrf
                @method('PUT')

                <div class="card-header">
                    <h3 class="card-title">
                        Personal information
                    </h3>
                </div>

                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">
                            Name
                        </label>
                        <input
                            type="text"
                            name="name"
                            value="{{ old('name', $admin->name) }}"
                            class="form-control @error('name') is-invalid @enderror"
                        >
                        @error('name')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            Email
                        </label>
                        <input
                            type="email"
                            value="{{ $admin->email }}"
                            class="form-control"
                            disabled
                        >
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                Mobile
                            </label>
                            <input
                                type="text"
                                name="mobile"
                                value="{{ old('mobile', $admin->mobile) }}"
                                class="form-control"
                            >
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                Country
                            </label>
                            <input
                                type="text"
                                name="country"
                                value="{{ old('country', $admin->country) }}"
                                class="form-control"
                            >
                        </div>
                    </div>
                </div>

                <div class="card-footer text-end">
                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Save profile
                    </button>
                </div>
            </form>
        </div>

        <div class="col-lg-5">
            <form
                method="POST"
                action="{{ route('admin.profile.password') }}"
                class="card"
            >
                @csrf
                @method('PUT')

                <div class="card-header">
                    <h3 class="card-title">
                        Change password
                    </h3>
                </div>

                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">
                            Current password
                        </label>
                        <input
                            type="password"
                            name="current_password"
                            class="form-control @error('current_password') is-invalid @enderror"
                        >
                        @error('current_password')
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
                        >
                        @error('password')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            Confirm new password
                        </label>
                        <input
                            type="password"
                            name="password_confirmation"
                            class="form-control"
                        >
                    </div>
                </div>

                <div class="card-footer text-end">
                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Update password
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
