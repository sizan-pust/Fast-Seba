@extends('layouts.admin.app')

@section('title', $title)

@section('content')
    @php
        $routeGroup = $routeGroup ?? 'manage';
        $editable = $editable ?? $edit ?? false;
        $deletable = $deletable ?? $delete ?? false;
        $query = $query ?? [];
    @endphp
    <div class="page-header d-print-none">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">
                    <a
                        href="{{ route(
                            'admin.'.$routeGroup.'.'.$module.'.index',
                            $query
                        ) }}"
                        class="text-secondary"
                    >
                        {{ str($module)->replace('-', ' ')->title() }}
                    </a>
                </div>
                <h2 class="page-title">{{ $title }}</h2>
                <div class="text-secondary mt-1">{{ $subtitle }}</div>
            </div>

            <div class="col-auto ms-auto">
                <div class="btn-list">
                    @if ($editable)
                        <a
                            href="{{ route(
                                'admin.'.$routeGroup.'.'.$module.'.edit',
                                array_merge(['id' => $recordId], $query)
                            ) }}"
                            class="btn btn-primary"
                        >
                            Edit
                        </a>
                    @endif
                    <a
                        href="{{ route(
                            'admin.'.$routeGroup.'.'.$module.'.index',
                            $query
                        ) }}"
                        class="btn btn-outline-secondary"
                    >
                        Back to list
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row row-cards">
        @foreach ($sections as $section)
            <div class="col-lg-{{ count($sections) > 1 ? '6' : '8' }}">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="card-title">{{ $section['title'] }}</h3>
                    </div>
                    <div class="card-body">
                        <dl class="row admin-detail-list">
                            @foreach ($section['items'] as $item)
                                <dt class="col-sm-5 text-secondary">
                                    {{ $item['label'] }}
                                </dt>
                                <dd class="col-sm-7 admin-detail-value">
                                    @if ($item['type'] === 'status')
                                        <span class="badge admin-status admin-status-{{ $item['value'] }}">
                                            {{ str((string) $item['value'])
                                                ->replace('_', ' ')
                                                ->title() }}
                                        </span>
                                    @elseif ($item['type'] === 'money')
                                        ৳{{ number_format((float) $item['value'], 2) }}
                                    @elseif ($item['type'] === 'date')
                                        @php
                                            $detailDate = '—';
                                            $detailRawValue =
                                                $item['value'] ?? null;

                                            $detailHasValue =
                                                ! is_null($detailRawValue)
                                                && $detailRawValue !== ''
                                                && $detailRawValue !== '—';

                                            if ($detailHasValue) {
                                                try {
                                                    $detailDate =
                                                        $detailRawValue instanceof \DateTimeInterface
                                                            ? $detailRawValue->format(
                                                                'd M Y H:i'
                                                            )
                                                            : \Illuminate\Support\Carbon::parse(
                                                                $detailRawValue
                                                            )->format(
                                                                'd M Y H:i'
                                                            );
                                                } catch (\Throwable) {
                                                    $detailDate = '—';
                                                }
                                            }
                                        @endphp
                                        {{ $detailDate }}
                                    @else
                                        <span class="text-break">{{ $item['value'] }}</span>
                                    @endif
                                </dd>
                            @endforeach
                        </dl>
                    </div>
                </div>
            </div>
        @endforeach

        @if ($actions !== [] || $deletable)
            <div class="col-lg-4">
                @foreach ($actions as $action)
                    <form
                        method="POST"
                        action="{{ route(
                            'admin.'.$routeGroup.'.'.$module.'.action',
                            array_merge(['id' => $recordId], $query)
                        ) }}"
                        class="card mb-3"
                        @if ($action['confirm'] ?? false)
                            onsubmit="return confirm('Are you sure?')"
                        @endif
                    >
                        @csrf
                        <input
                            type="hidden"
                            name="action"
                            value="{{ $action['action'] }}"
                        >
                        @foreach ($query as $key => $value)
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endforeach

                        <div class="card-header">
                            <h3 class="card-title">{{ $action['label'] }}</h3>
                        </div>
                        <div class="card-body">
                            @foreach ($action['fields'] as $field)
                                @if ($field['type'] === 'hidden')
                                    <input
                                        type="hidden"
                                        name="{{ $field['name'] }}"
                                        value="{{ $field['value'] }}"
                                    >
                                @elseif ($field['type'] === 'checkbox')
                                    <label class="form-check mb-3">
                                        <input
                                            type="checkbox"
                                            name="{{ $field['name'] }}"
                                            value="1"
                                            class="form-check-input"
                                        >
                                        <span class="form-check-label">
                                            {{ $field['label'] }}
                                        </span>
                                    </label>
                                @elseif ($field['type'] === 'textarea')
                                    <div class="mb-3">
                                        <label class="form-label">{{ $field['label'] }}</label>
                                        <textarea
                                            name="{{ $field['name'] }}"
                                            class="form-control"
                                            rows="3"
                                            @required($field['required'] ?? false)
                                        >{{ $field['value'] ?? '' }}</textarea>
                                    </div>
                                @elseif ($field['type'] === 'select')
                                    <div class="mb-3">
                                        <label class="form-label">{{ $field['label'] }}</label>
                                        <select
                                            name="{{ $field['name'] }}"
                                            class="form-select"
                                            @required($field['required'] ?? false)
                                        >
                                            @if ($field['nullable'] ?? false)
                                                <option value="">No change</option>
                                            @endif
                                            @foreach ($field['options'] as $value => $label)
                                                <option
                                                    value="{{ $value }}"
                                                    @selected(
                                                        (string) ($field['value'] ?? '')
                                                        === (string) $value
                                                    )
                                                >
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                @else
                                    <div class="mb-3">
                                        <label class="form-label">{{ $field['label'] }}</label>
                                        <input
                                            type="text"
                                            name="{{ $field['name'] }}"
                                            value="{{ $field['value'] ?? '' }}"
                                            class="form-control"
                                            @required($field['required'] ?? false)
                                        >
                                    </div>
                                @endif
                            @endforeach

                            <button
                                type="submit"
                                class="btn btn-{{ $action['tone'] ?? 'primary' }} w-100"
                            >
                                {{ $action['label'] }}
                            </button>
                        </div>
                    </form>
                @endforeach

                @if ($deletable)
                    <form
                        method="POST"
                        action="{{ route(
                            'admin.'.$routeGroup.'.'.$module.'.destroy',
                            array_merge(['id' => $recordId], $query)
                        ) }}"
                        class="card"
                        onsubmit="return confirm('Delete this record permanently?')"
                    >
                        @csrf
                        @method('DELETE')
                        <div class="card-body">
                            <button
                                type="submit"
                                class="btn btn-outline-danger w-100"
                            >
                                Delete record
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        @endif

        @foreach ($tables as $table)
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ $table['title'] }}</h3>
                    </div>
                    <div class="table-responsive">
                        <table class="table card-table table-vcenter">
                            <thead>
                                <tr>
                                    @foreach ($table['columns'] as $column)
                                        <th>{{ $column }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($table['rows'] as $row)
                                    <tr>
                                        @foreach ($row as $value)
                                            <td class="text-break">{{ $value }}</td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr>
                                        <td
                                            colspan="{{ count($table['columns']) }}"
                                            class="text-center text-secondary py-5"
                                        >
                                            No related records.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
