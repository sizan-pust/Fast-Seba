<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminCoreResourceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminCoreResourceController extends Controller
{
    public function __construct(
        private readonly AdminCoreResourceService $resources
    ) {
    }

    public function index(Request $request, string $module): View
    {
        return view('admin.core.index', $this->resources->index($module, $request));
    }

    public function show(
        string $id,
        string $module
    ): View {
        return view(
            'admin.core.show',
            $this->resources->show(
                $module,
                (int) $id
            )
        );
    }

    public function updateState(
        Request $request,
        string $id,
        string $module
    ): RedirectResponse {
        $this->resources->updateState(
            $module,
            (int) $id,
            $request
        );

        return back()->with(
            'success',
            'State updated successfully.'
        );
    }

    public function assignOrder(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'delivery_boy_id' => ['required', 'integer', 'exists:delivery_boys,id'],
        ]);

        $this->resources->assignOrder($id, (int) $data['delivery_boy_id']);

        return back()->with('success', 'Delivery partner assigned.');
    }

    public function assignReturn(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'delivery_boy_id' => ['required', 'integer', 'exists:delivery_boys,id'],
        ]);

        $this->resources->assignReturn($id, (int) $data['delivery_boy_id']);

        return back()->with('success', 'Return pickup assigned.');
    }

    public function refundReturn(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->resources->refundReturn($id, $data['comment'] ?? null);

        return back()->with('success', 'Refund processed to the customer wallet.');
    }

    public function reviewPrescription(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:under_review,approved,rejected'],
            'seller_id' => ['nullable', 'integer', 'exists:sellers,id'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'review_notes' => ['nullable', 'string', 'max:2000'],
            'rejection_reason' => ['nullable', 'string', 'max:2000'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        $this->resources->reviewPrescription($id, $data);

        return back()->with('success', 'Prescription review updated.');
    }
}
