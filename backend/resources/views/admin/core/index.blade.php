@extends('layouts.admin.app')

@section('title', $meta['title'])

@section('content')
    <div class="page-header d-print-none admin-resource-header">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">Administration</div>
                <h2 class="page-title">{{ $meta['title'] }}</h2>
                <div class="text-secondary mt-1">{{ $meta['description'] }}</div>
            </div>
        </div>
    </div>

    <div class="row row-cards mb-3">
        @foreach ($stats as $label => $value)
            <div class="col-sm-6 col-lg-3">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="subheader">{{ $label }}</div>
                        <div class="h2 mb-0">{{ number_format((float) $value) }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-header admin-table-toolbar">
            <form method="GET" action="{{ route('admin.core.'.$module.'.index') }}" class="row g-2 w-100 align-items-end">
                <div class="col-sm-6 col-lg">
                    <label class="form-label">Search</label>
                    <input type="search" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Search {{ strtolower($meta['title']) }}">
                </div>

                @foreach ($filters as $key => $options)
                    @if ($key === 'low_stock')
                        <div class="col-sm-6 col-lg-auto">
                            <label class="form-check mt-4">
                                <input type="checkbox" name="low_stock" value="1" class="form-check-input" @checked(request()->boolean('low_stock'))>
                                <span class="form-check-label">Low stock only</span>
                            </label>
                        </div>
                    @else
                        <div class="col-sm-6 col-lg-auto">
                            <label class="form-label">{{ str($key)->replace('_', ' ')->title() }}</label>
                            <select name="{{ $key }}" class="form-select form-select-sm">
                                <option value="">All</option>
                                @foreach ($options as $optionKey => $optionValue)
                                    @php
                                        $value = is_string($optionKey) && ! is_numeric($optionKey) ? $optionKey : $optionValue;
                                        $label = $optionValue;
                                    @endphp
                                    <option value="{{ $value }}" @selected((string) request($key) === (string) $value)>
                                        {{ str((string) $label)->replace('_', ' ')->title() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                @endforeach

                <div class="col-sm-6 col-lg-auto">
                    <label class="form-label">Rows</label>
                    <select name="per_page" class="form-select form-select-sm">
                        @foreach ([20, 50, 100] as $size)
                            <option value="{{ $size }}" @selected((int) request('per_page', 20) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-sm-auto">
                    <button type="submit" class="btn btn-primary btn-sm">Apply filters</button>
                    <a href="{{ route('admin.core.'.$module.'.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table card-table table-vcenter table-hover">
                <thead>
                    <tr>
                        @foreach ($columns as $column)
                            <th>{{ str($column)->replace('_', ' ')->title() }}</th>
                        @endforeach
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $row)
                        <tr>
                            @foreach ($columns as $index => $column)
                                @php
                                    $value = $row[$column] ?? '—';
                                    $isStatus = in_array($column, ['status', 'payment', 'verification', 'pickup'], true);
                                    $isMoney = in_array($column, ['total', 'refund', 'price'], true);
                                    $isDate = in_array($column, ['created', 'joined'], true);
                                @endphp
                                <td>
                                    @if ($index === 0)
                                        <a href="{{ route('admin.core.'.$module.'.show', $row['id']) }}" class="fw-semibold">{{ $value }}</a>
                                    @elseif ($isStatus)
                                        <span class="badge admin-status admin-status-{{ $value }}">{{ str((string) $value)->replace('_', ' ')->title() }}</span>
                                    @elseif ($isMoney)
                                        ৳{{ number_format((float) $value, 2) }}
                                    @elseif ($isDate)
                                        {{ $value ? \Illuminate\Support\Carbon::parse($value)->format('d M Y H:i') : '—' }}
                                    @else
                                        {{ $value }}
                                    @endif
                                </td>
                            @endforeach
                            <td><a href="{{ route('admin.core.'.$module.'.show', $row['id']) }}" class="btn btn-icon btn-sm btn-ghost-primary" title="View details">→</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($columns) + 1 }}" class="text-center text-secondary py-5">No matching records were found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($records->hasPages())
            <div class="card-footer d-flex align-items-center">
                <p class="m-0 text-secondary">Showing {{ $records->firstItem() }} to {{ $records->lastItem() }} of {{ $records->total() }} records</p>
                <div class="ms-auto">{{ $records->onEachSide(1)->links() }}</div>
            </div>
        @endif
    </div>
@endsection
