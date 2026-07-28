@extends('layouts.admin.app')

@section('title', $title)

@section('content')
    @php
        $routeQuery = array_filter(['view' => $view === 'default' ? null : $view]);
        $action = $isEdit
            ? route(
                'admin.manage.'.$module.'.update',
                array_merge(['id' => $model->getKey()], $routeQuery)
            )
            : route('admin.manage.'.$module.'.store', $routeQuery);
    @endphp

    <div class="page-header d-print-none">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">
                    {{ str($module)->replace('-', ' ')->title() }}
                </div>
                <h2 class="page-title">{{ $title }}</h2>
            </div>
            <div class="col-auto ms-auto">
                <a
                    href="{{ route('admin.manage.'.$module.'.index', $routeQuery) }}"
                    class="btn btn-outline-secondary"
                >
                    Back to list
                </a>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ $action }}" class="card">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif
        @if ($view !== 'default')
            <input type="hidden" name="view" value="{{ $view }}">
        @endif

        <div class="card-body">
            @if ($formType === 'faq')
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Category</label>
                        <input
                            type="text"
                            name="category"
                            value="{{ old('category', $model->category) }}"
                            class="form-control"
                        >
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Sort order</label>
                        <input
                            type="number"
                            name="sort_order"
                            min="0"
                            value="{{ old('sort_order', $model->sort_order ?: 0) }}"
                            class="form-control"
                        >
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label required">Status</label>
                        <select name="status" class="form-select" required>
                            @foreach (['active', 'inactive'] as $status)
                                <option
                                    value="{{ $status }}"
                                    @selected(
                                        old('status', $model->status ?: 'active')
                                        === $status
                                    )
                                >
                                    {{ str($status)->title() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label required">Question</label>
                        <textarea
                            name="question"
                            class="form-control"
                            rows="3"
                            required
                        >{{ old('question', $model->question) }}</textarea>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label required">Answer</label>
                        <textarea
                            name="answer"
                            class="form-control"
                            rows="6"
                            required
                        >{{ old('answer', $model->answer) }}</textarea>
                    </div>
                </div>
            @elseif ($formType === 'notification')
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label required">Audience</label>
                        <select name="audience_type" class="form-select" required>
                            @foreach (['all', 'customer', 'seller', 'delivery_boy', 'users'] as $audience)
                                <option
                                    value="{{ $audience }}"
                                    @selected(old('audience_type', 'all') === $audience)
                                >
                                    {{ str($audience)->replace('_', ' ')->title() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-8 mb-3">
                        <label class="form-label required">Title</label>
                        <input
                            type="text"
                            name="title"
                            value="{{ old('title') }}"
                            class="form-control"
                            required
                        >
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label required">Message</label>
                        <textarea
                            name="message"
                            class="form-control"
                            rows="5"
                            required
                        >{{ old('message') }}</textarea>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Schedule</label>
                        <input
                            type="datetime-local"
                            name="scheduled_at"
                            value="{{ old('scheduled_at') }}"
                            class="form-control"
                        >
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Target type</label>
                        <input
                            type="text"
                            name="target_type"
                            value="{{ old('target_type') }}"
                            class="form-control"
                        >
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Target ID</label>
                        <input
                            type="number"
                            name="target_id"
                            value="{{ old('target_id') }}"
                            class="form-control"
                        >
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Delivery zones</label>
                        <select name="zone_ids[]" class="form-select" multiple size="8">
                            @foreach ($zones as $zone)
                                <option
                                    value="{{ $zone->id }}"
                                    @selected(
                                        collect(old('zone_ids', []))
                                            ->contains((string) $zone->id)
                                    )
                                >
                                    {{ $zone->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Specific users</label>
                        <select name="user_ids[]" class="form-select" multiple size="8">
                            @foreach ($users as $user)
                                <option
                                    value="{{ $user->id }}"
                                    @selected(
                                        collect(old('user_ids', []))
                                            ->contains((string) $user->id)
                                    )
                                >
                                    {{ $user->name }} · {{ $user->email }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            @elseif ($formType === 'gateway')
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label class="form-label required">Display name</label>
                        <input
                            type="text"
                            name="display_name"
                            value="{{ old('display_name', $model->display_name) }}"
                            class="form-control"
                            required
                        >
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Code</label>
                        <input type="text" value="{{ $model->code }}" class="form-control" disabled>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Sort order</label>
                        <input
                            type="number"
                            name="sort_order"
                            min="0"
                            value="{{ old('sort_order', $model->sort_order) }}"
                            class="form-control"
                        >
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-check mt-4">
                            <input
                                type="checkbox"
                                name="enabled"
                                value="1"
                                class="form-check-input"
                                @checked(old('enabled', $model->enabled))
                            >
                            <span class="form-check-label">Enabled</span>
                        </label>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-check mt-4">
                            <input
                                type="checkbox"
                                name="test_mode"
                                value="1"
                                class="form-check-input"
                                @checked(old('test_mode', $model->test_mode))
                            >
                            <span class="form-check-label">Test mode</span>
                        </label>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Currencies</label>
                        <input
                            type="text"
                            name="supported_currencies"
                            value="{{ old(
                                'supported_currencies',
                                collect($model->supported_currencies ?? [])->implode(', ')
                            ) }}"
                            class="form-control"
                            placeholder="BDT, USD"
                        >
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Public configuration JSON</label>
                        <textarea
                            name="public_config_json"
                            class="form-control font-monospace"
                            rows="10"
                        >{{ old(
                            'public_config_json',
                            json_encode($model->public_config ?? [], JSON_PRETTY_PRINT)
                        ) }}</textarea>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Secret configuration JSON</label>
                        <textarea
                            name="secret_config_json"
                            class="form-control font-monospace"
                            rows="10"
                            placeholder="Leave empty to keep existing secrets"
                        ></textarea>
                    </div>
                </div>
            @endif
        </div>

        <div class="card-footer text-end">
            <button type="submit" class="btn btn-primary">
                {{ $isEdit ? 'Save changes' : 'Create record' }}
            </button>
        </div>
    </form>
@endsection
