<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Services\Seller\SellerPanelResourceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SellerResourceController extends Controller
{
    public function __construct(
        private readonly SellerPanelResourceService $resources
    ) {
    }

    public function index(Request $request, string $module): View
    {
        return view(
            'seller.manage.index',
            $this->resources->index(
                $module,
                $request,
                Auth::guard('seller')->user(),
                $request->attributes->get('seller')
            )
        );
    }

    public function create(Request $request, string $module): View
    {
        return view(
            'seller.manage.form',
            $this->resources->form(
                $module,
                $request,
                Auth::guard('seller')->user(),
                $request->attributes->get('seller')
            )
        );
    }

    public function store(Request $request, string $module): RedirectResponse
    {
        $record = $this->resources->save(
            $module,
            $request,
            Auth::guard('seller')->user(),
            $request->attributes->get('seller')
        );

        return redirect()
            ->route('seller.resource.'.$module.'.show', array_filter(['id' => $record->getKey(), 'view' => $request->input('view')]))
            ->with('success', 'Record created successfully.');
    }

    public function show(Request $request, string $id, string $module): View
    {
        return view(
            'seller.manage.show',
            $this->resources->show(
                $module,
                $id,
                $request,
                Auth::guard('seller')->user(),
                $request->attributes->get('seller')
            )
        );
    }

    public function edit(Request $request, string $id, string $module): View
    {
        return view(
            'seller.manage.form',
            $this->resources->form(
                $module,
                $request,
                Auth::guard('seller')->user(),
                $request->attributes->get('seller'),
                $id
            )
        );
    }

    public function update(Request $request, string $id, string $module): RedirectResponse
    {
        $record = $this->resources->save(
            $module,
            $request,
            Auth::guard('seller')->user(),
            $request->attributes->get('seller'),
            $id
        );

        return redirect()
            ->route('seller.resource.'.$module.'.show', array_filter(['id' => $record->getKey(), 'view' => $request->input('view')]))
            ->with('success', 'Record updated successfully.');
    }

    public function destroy(Request $request, string $id, string $module): RedirectResponse
    {
        $this->resources->destroy(
            $module,
            $id,
            $request,
            Auth::guard('seller')->user(),
            $request->attributes->get('seller')
        );

        return redirect()
            ->route('seller.resource.'.$module.'.index', array_filter(['view' => $request->input('view')]))
            ->with('success', 'Record deleted successfully.');
    }

    public function action(Request $request, string $id, string $module): RedirectResponse
    {
        $message = $this->resources->action(
            $module,
            $id,
            $request,
            Auth::guard('seller')->user(),
            $request->attributes->get('seller')
        );

        return back()->with('success', $message);
    }

    public function pageAction(Request $request, string $module): RedirectResponse|StreamedResponse|BinaryFileResponse
    {
        $result = $this->resources->pageAction(
            $module,
            $request,
            Auth::guard('seller')->user(),
            $request->attributes->get('seller')
        );

        if ($result instanceof StreamedResponse || $result instanceof BinaryFileResponse) {
            return $result;
        }

        return back()->with('success', $result);
    }
}
