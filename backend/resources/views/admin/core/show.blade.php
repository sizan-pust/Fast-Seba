@extends('layouts.admin.app')

@section('title', $title)

@section('content')
    <div class="page-header d-print-none">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle"><a href="{{ route('admin.core.'.$module.'.index') }}" class="text-secondary">{{ $meta['title'] }}</a></div>
                <h2 class="page-title">{{ $title }}</h2>
            </div>
            <div class="col-auto ms-auto"><a href="{{ route('admin.core.'.$module.'.index') }}" class="btn btn-outline-secondary">Back to list</a></div>
        </div>
    </div>

    <div class="row row-cards">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Details</h3></div>
                <div class="card-body">
                    <dl class="row admin-detail-list">
                        @foreach ($details as $key => $value)
                            <dt class="col-sm-5 text-secondary">{{ str($key)->replace('_', ' ')->title() }}</dt>
                            <dd class="col-sm-7">
                                @if (in_array($key, ['status', 'payment_status', 'verification_status', 'visibility_status', 'return_status', 'pickup_status'], true))
                                    <span class="badge admin-status admin-status-{{ $value }}">{{ str((string) $value)->replace('_', ' ')->title() }}</span>
                                @elseif (in_array($key, ['subtotal', 'delivery_charge', 'final_total', 'refund_amount', 'price', 'special_price', 'cost', 'regular_delivery_charges', 'rush_delivery_charges', 'free_delivery_amount', 'reward_points'], true))
                                    ৳{{ number_format((float) $value, 2) }}
                                @elseif (str_ends_with($key, '_at') || $key === 'created_at')
                                    {{ $value ? \Illuminate\Support\Carbon::parse($value)->format('d M Y H:i') : '—' }}
                                @elseif (is_bool($value))
                                    {{ $value ? 'Yes' : 'No' }}
                                @else
                                    {{ $value === null || $value === '' ? '—' : $value }}
                                @endif
                            </dd>
                        @endforeach
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            @if ($stateControls !== [])
                <div class="card mb-3">
                    <div class="card-header"><h3 class="card-title">Administrative state</h3></div>
                    <div class="card-body">
                        @foreach ($stateControls as $control)
                            <form method="POST" action="{{ route('admin.core.'.$module.'.state', $recordId) }}" class="mb-4">
                                @csrf
                                <input type="hidden" name="field" value="{{ $control['field'] }}">
                                <label class="form-label">{{ $control['label'] }}</label>
                                <div class="input-group">
                                    <select name="value" class="form-select">
                                        @foreach ($control['options'] as $option)
                                            <option value="{{ $option }}" @selected($control['current'] === $option)>{{ str($option)->replace('_', ' ')->title() }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-primary">Update</button>
                                </div>
                                @if ($control['field'] === 'verification_status')
                                    <textarea name="reason" class="form-control mt-2" rows="2" placeholder="Optional verification note"></textarea>
                                @endif
                            </form>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($special)
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Actions</h3></div>
                    <div class="card-body">
                        @if ($special['type'] === 'assign-order')
                            @if ($special['enabled'])
                                <form method="POST" action="{{ route('admin.orders.assign-rider', $recordId) }}">
                                    @csrf
                                    <label class="form-label">Assign delivery partner</label>
                                    <select name="delivery_boy_id" class="form-select mb-3" required>
                                        <option value="">Select partner</option>
                                        @foreach ($special['riders'] as $rider)
                                            <option value="{{ $rider->id }}">{{ $rider->user?->name }}{{ $rider->user?->mobile ? ' · '.$rider->user->mobile : '' }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-primary w-100">Assign partner</button>
                                </form>
                            @else
                                <p class="text-secondary mb-0">This order is not waiting for a new delivery assignment.</p>
                            @endif
                        @elseif ($special['type'] === 'return-actions')
                            @if ($special['canAssign'])
                                <form method="POST" action="{{ route('admin.returns.assign-rider', $recordId) }}" class="mb-4">
                                    @csrf
                                    <label class="form-label">Assign return pickup</label>
                                    <select name="delivery_boy_id" class="form-select mb-3" required>
                                        <option value="">Select partner</option>
                                        @foreach ($special['riders'] as $rider)
                                            <option value="{{ $rider->id }}">{{ $rider->user?->name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-primary w-100">Assign pickup</button>
                                </form>
                            @endif
                            @if ($special['canRefund'])
                                <form method="POST" action="{{ route('admin.returns.refund', $recordId) }}">
                                    @csrf
                                    <label class="form-label">Refund to customer wallet</label>
                                    <textarea name="comment" class="form-control mb-3" rows="3" placeholder="Optional admin comment"></textarea>
                                    <button type="submit" class="btn btn-success w-100">Process refund</button>
                                </form>
                            @endif
                            @if (! $special['canAssign'] && ! $special['canRefund'])
                                <p class="text-secondary mb-0">No administrative action is currently available for this return.</p>
                            @endif
                        @elseif ($special['type'] === 'prescription-review')
                            <form method="POST" action="{{ route('admin.prescriptions.review', $recordId) }}" id="prescription-review-form">
                                @csrf
                                <label class="form-label">Decision</label>
                                <select name="status" class="form-select mb-3" id="prescription-status" required>
                                    <option value="under_review">Under review</option>
                                    <option value="approved">Approve</option>
                                    <option value="rejected">Reject</option>
                                </select>
                                <div id="prescription-assignment">
                                    <label class="form-label">Seller</label>
                                    <select name="seller_id" class="form-select mb-3" id="prescription-seller">
                                        <option value="">Select seller</option>
                                        @foreach ($special['sellers'] as $seller)
                                            <option value="{{ $seller->id }}" data-stores='@json($seller->stores->map(fn ($store) => ["id" => $store->id, "name" => $store->name])->values())'>{{ $seller->business_name }}</option>
                                        @endforeach
                                    </select>
                                    <label class="form-label">Store</label>
                                    <select name="store_id" class="form-select mb-3" id="prescription-store"><option value="">Select store</option></select>
                                    <label class="form-label">Expires at</label>
                                    <input type="date" name="expires_at" class="form-control mb-3" min="{{ now()->addDay()->toDateString() }}">
                                </div>
                                <label class="form-label">Review notes</label>
                                <textarea name="review_notes" class="form-control mb-3" rows="3"></textarea>
                                <div id="prescription-rejection">
                                    <label class="form-label">Rejection reason</label>
                                    <textarea name="rejection_reason" class="form-control mb-3" rows="3"></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Save review</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        @if (($related['rows'] ?? []) !== [])
            <div class="col-12">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">{{ $related['title'] }}</h3></div>
                    <div class="table-responsive">
                        <table class="table card-table table-vcenter">
                            <tbody>
                                @foreach ($related['rows'] as $row)
                                    <tr>@foreach ($row as $value)<td>{{ $value }}</td>@endforeach</tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const status = document.getElementById('prescription-status');
    const assignment = document.getElementById('prescription-assignment');
    const rejection = document.getElementById('prescription-rejection');
    const seller = document.getElementById('prescription-seller');
    const store = document.getElementById('prescription-store');

    function updateFields() {
        if (! status) return;
        assignment?.classList.toggle('d-none', status.value !== 'approved');
        rejection?.classList.toggle('d-none', status.value !== 'rejected');
    }

    function updateStores() {
        if (! seller || ! store) return;
        store.innerHTML = '<option value="">Select store</option>';
        JSON.parse(seller.options[seller.selectedIndex]?.dataset?.stores || '[]').forEach(function (item) {
            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = item.name;
            store.appendChild(option);
        });
    }

    status?.addEventListener('change', updateFields);
    seller?.addEventListener('change', updateStores);
    updateFields();
    updateStores();
});
</script>
@endpush
