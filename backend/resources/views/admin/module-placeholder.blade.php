@extends('layouts.admin.app')

@section('title', $item['title'])

@section('content')
    <div class="page-header d-print-none">
        <div class="row align-items-center">
            <div class="col">
                <div class="page-pretitle">
                    FastSheba Admin
                </div>
                <h2 class="page-title">
                    {{ $item['title'] }}
                </h2>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="empty">
            <div class="empty-img">
                <span class="avatar avatar-xl bg-primary-lt text-primary">
                    <x-admin.icon
                        :name="$item['icon'] ?? 'dashboard'"
                        size="38"
                    />
                </span>
            </div>

            <p class="empty-title">
                The visual shell is ready
            </p>

            <p class="empty-subtitle text-secondary">
                The Phase 1–9 backend for this module already exists.
                Its same-style management screens will be connected in
                the next Admin Panel implementation batch.
            </p>

            <div class="empty-action">
                <a
                    href="{{ route('admin.dashboard') }}"
                    class="btn btn-primary"
                >
                    Return to dashboard
                </a>
            </div>
        </div>
    </div>
@endsection
