@extends('layouts.admin.app')

@section('title', $title)

@section('content')
    @php
        $routeGroup = $routeGroup ?? 'manage';
        $view = $view ?? $currentTab ?? 'default';
        $canCreate = $canCreate ?? $create ?? false;
        $canSync = $canSync ?? false;
        $pageActions = $pageActions ?? [];
    @endphp
    <div class="page-header d-print-none admin-resource-header">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">Administration</div>
                <h2 class="page-title">{{ $title }}</h2>
                <div class="text-secondary mt-1">{{ $description }}</div>
            </div>

            <div class="col-auto ms-auto d-print-none">
                <div class="btn-list">
                    @if ($canSync)
                        <form
                            method="POST"
                            action="{{ route('admin.'.$routeGroup.'.'.$module.'.page-action') }}"
                        >
                            @csrf
                            <input
                                type="hidden"
                                name="action"
                                value="{{ $module === 'seller-statements' ? 'sync' : 'sync-referrals' }}"
                            >
                            <input type="hidden" name="view" value="{{ $view }}">
                            <button type="submit" class="btn btn-outline-primary">
                                Sync records
                            </button>
                        </form>
                    @endif

                    @foreach ($pageActions as $pageAction)
                        <form
                            method="POST"
                            action="{{ route(
                                'admin.'
                                    .$routeGroup
                                    .'.'
                                    .$module
                                    .'.page-action',
                                $view === 'default'
                                    ? []
                                    : ['view' => $view]
                            ) }}"
                        >
                            @csrf
                            <input
                                type="hidden"
                                name="action"
                                value="{{ $pageAction['action'] }}"
                            >
                            @if ($view !== 'default')
                                <input
                                    type="hidden"
                                    name="view"
                                    value="{{ $view }}"
                                >
                            @endif
                            <button
                                type="submit"
                                class="btn btn-{{ $pageAction['tone'] ?? 'primary' }}"
                            >
                                {{ $pageAction['label'] }}
                            </button>
                        </form>
                    @endforeach

                    @if ($canCreate)
                        <a
                            href="{{ route(
                                'admin.'.$routeGroup.'.'.$module.'.create',
                                ['view' => $view]
                            ) }}"
                            class="btn btn-primary"
                        >
                            + Create
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if ($tabs !== [])
        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="nav nav-pills">
                    @foreach ($tabs as $tab => $label)
                        <a
                            href="{{ route(
                                'admin.'.$routeGroup.'.'.$module.'.index',
                                ['view' => $tab]
                            ) }}"
                            class="nav-link {{ $view === $tab ? 'active' : '' }}"
                        >
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <div class="row row-cards mb-3">
        @foreach ($stats as $label => $value)
            <div class="col-sm-6 col-lg-3">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="subheader">{{ $label }}</div>
                        <div class="h2 mb-0">
                            @if (
                                is_numeric($value)
                                && (
                                    str_contains(strtolower($label), 'amount')
                                    || str_contains(strtolower($label), 'value')
                                    || str_contains(strtolower($label), 'spent')
                                    || str_contains(strtolower($label), 'credit')
                                    || str_contains(strtolower($label), 'debit')
                                )
                            )
                                ৳{{ number_format((float) $value, 2) }}
                            @elseif (is_numeric($value))
                                {{ number_format((float) $value) }}
                            @else
                                {{ $value }}
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-header admin-table-toolbar">
            <form
                method="GET"
                action="{{ route('admin.'.$routeGroup.'.'.$module.'.index') }}"
                class="row g-2 w-100 align-items-end"
            >
                @if ($view !== 'default')
                    <input type="hidden" name="view" value="{{ $view }}">
                @endif

                <div class="col-sm-6 col-lg">
                    <label class="form-label">Search</label>
                    <input
                        type="search"
                        name="search"
                        value="{{ request('search') }}"
                        class="form-control form-control-sm"
                        placeholder="Search {{ strtolower($title) }}"
                    >
                </div>

                @foreach ($filters as $filter)
                    <div class="col-sm-6 col-lg-auto">
                        <label class="form-label">{{ $filter['label'] }}</label>
                        <select
                            name="{{ $filter['key'] }}"
                            class="form-select form-select-sm"
                        >
                            <option value="">All</option>

                            @foreach ($filter['options'] as $key => $label)
                                @php
                                    $optionValue = $filter['type'] === 'select-map'
                                        ? $key
                                        : $label;
                                @endphp
                                <option
                                    value="{{ $optionValue }}"
                                    @selected(
                                        (string) request($filter['key'])
                                        === (string) $optionValue
                                    )
                                >
                                    {{ str((string) $label)
                                        ->replace('_', ' ')
                                        ->title() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endforeach

                <div class="col-sm-6 col-lg-auto">
                    <label class="form-label">Rows</label>
                    <select
                        name="per_page"
                        class="form-select form-select-sm"
                    >
                        @foreach ([20, 50, 100] as $size)
                            <option
                                value="{{ $size }}"
                                @selected((int) request('per_page', 20) === $size)
                            >
                                {{ $size }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-sm-auto">
                    <button type="submit" class="btn btn-primary btn-sm">
                        Apply filters
                    </button>
                    <a
                        href="{{ route(
                            'admin.'.$routeGroup.'.'.$module.'.index',
                            $view === 'default' ? [] : ['view' => $view]
                        ) }}"
                        class="btn btn-outline-secondary btn-sm"
                    >
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table card-table table-vcenter table-hover">
                <thead>
                    <tr>
                        @foreach ($columns as $column)
                            <th>{{ $column['label'] }}</th>
                        @endforeach
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $row)
                        <tr>
                            @foreach ($columns as $index => $column)
                                @php
                                    $rawValue = $row[$column['key']] ?? null;
                                    $type = $column['type'] ?? 'text';

                                    $hasValue = ! is_null($rawValue)
                                        && $rawValue !== ''
                                        && $rawValue !== '—';

                                    $displayValue = $hasValue
                                        ? $rawValue
                                        : '—';

                                    $formattedDate = '—';

                                    if ($type === 'date' && $hasValue) {
                                        try {
                                            $formattedDate =
                                                $rawValue instanceof \DateTimeInterface
                                                    ? $rawValue->format('d M Y H:i')
                                                    : \Illuminate\Support\Carbon::parse(
                                                        $rawValue
                                                    )->format('d M Y H:i');
                                        } catch (\Throwable) {
                                            $formattedDate = '—';
                                        }
                                    }
                                @endphp
                                <td>
                                    @if ($index === 0)
                                        <a
                                            href="{{ route(
                                                'admin.'.$routeGroup.'.'.$module.'.show',
                                                array_filter([
                                                    'id' => $row['id'],
                                                    'view' => $view === 'default'
                                                        ? null
                                                        : $view,
                                                ])
                                            ) }}"
                                            class="fw-semibold"
                                        >
                                            {{ $displayValue }}
                                        </a>
                                    @elseif ($type === 'status')
                                        @if ($hasValue)
                                            <span class="badge admin-status admin-status-{{ $displayValue }}">
                                                {{ str((string) $displayValue)
                                                    ->replace('_', ' ')
                                                    ->title() }}
                                            </span>
                                        @else
                                            —
                                        @endif
                                    @elseif ($type === 'money')
                                        @if ($hasValue)
                                            ৳{{ number_format(
                                                (float) $rawValue,
                                                2
                                            ) }}
                                        @else
                                            —
                                        @endif
                                    @elseif ($type === 'date')
                                        {{ $formattedDate }}
                                    @else
                                        {{ $displayValue }}
                                    @endif
                                </td>
                            @endforeach
                            <td>
                                <a
                                    href="{{ route(
                                        'admin.'.$routeGroup.'.'.$module.'.show',
                                        array_filter([
                                            'id' => $row['id'],
                                            'view' => $view === 'default'
                                                ? null
                                                : $view,
                                        ])
                                    ) }}"
                                    class="btn btn-icon btn-sm btn-ghost-primary"
                                    title="View details"
                                >
                                    →
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="{{ count($columns) + 1 }}"
                                class="text-center text-secondary py-5"
                            >
                                No matching records were found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($records->hasPages())
            <div class="card-footer d-flex align-items-center">
                <p class="m-0 text-secondary">
                    Showing {{ $records->firstItem() }} to
                    {{ $records->lastItem() }} of
                    {{ $records->total() }} records
                </p>
                <div class="ms-auto">
                    {{ $records->onEachSide(1)->links() }}
                </div>
            </div>
        @endif
    </div>
@endsection
