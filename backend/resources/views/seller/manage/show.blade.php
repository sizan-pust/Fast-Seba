@extends('layouts.seller.app')

@section('title', $title)

@section('content')
    @php($query = $query ?? [])

    <div class="page-header mb-3">
        <div class="row align-items-center">
            <div class="col">
                <div class="page-pretitle">Seller Operations</div>
                <h2 class="page-title">{{ $title }}</h2>
            </div>

            <div class="col-auto">
                <div class="btn-list">
                    <a href="{{ route('seller.resource.'.$module.'.index', $query) }}"
                       class="btn btn-outline-secondary">
                        Back
                    </a>

                    @if ($editable ?? false)
                        <a href="{{ route('seller.resource.'.$module.'.edit', array_merge(['id' => $recordId], $query)) }}"
                           class="btn btn-primary">
                            Edit
                        </a>
                    @endif

                    @if ($deletable ?? false)
                        <form method="POST"
                              action="{{ route('seller.resource.'.$module.'.destroy', array_merge(['id' => $recordId], $query)) }}"
                              onsubmit="return confirm('Delete this record?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">Delete</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row row-cards">
        @foreach ($sections as $section)
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="card-title">{{ $section['title'] }}</h3>
                    </div>

                    <div class="card-body">
                        <dl class="row mb-0">
                            @foreach ($section['items'] as $item)
                                <dt class="col-sm-5 text-secondary mb-2">{{ $item['label'] }}</dt>
                                <dd class="col-sm-7 mb-2">
                                    @if (($item['format'] ?? '') === 'pre')
                                        <pre class="small bg-body-secondary p-2 rounded mb-0">{{ $item['value'] }}</pre>
                                    @elseif (($item['format'] ?? '') === 'status')
                                        <span class="badge bg-blue-lt">
                                            {{ str((string) $item['value'])->replace('_', ' ')->title() }}
                                        </span>
                                    @else
                                        {!! nl2br(e($item['value'] ?? '—')) !!}
                                    @endif
                                </dd>
                            @endforeach
                        </dl>
                    </div>
                </div>
            </div>
        @endforeach

        @foreach ($actions ?? [] as $action)
            <div class="col-lg-6">
                <div class="card seller-action-card h-100">
                    <div class="card-header">
                        <h3 class="card-title">{{ $action['label'] }}</h3>
                    </div>

                    <form method="POST"
                          action="{{ route('seller.resource.'.$module.'.action', array_merge(['id' => $recordId], $query)) }}">
                        @csrf

                        <div class="card-body">
                            <div class="row g-3">
                                @foreach ($action['fields'] ?? [] as $field)
                                    <div class="{{ ($field['type'] ?? '') === 'hidden' ? 'd-none' : 'col-12' }}">
                                        @include('seller.manage._field', ['field' => $field])
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="card-footer text-end">
                            <button type="submit" class="btn btn-{{ $action['tone'] ?? 'primary' }}">
                                {{ $action['label'] }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach

        @foreach ($tables ?? [] as $table)
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
                                        @foreach ($row as $cell)
                                            <td>{{ $cell }}</td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ count($table['columns']) }}" class="seller-table-empty">
                                            No records.
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
