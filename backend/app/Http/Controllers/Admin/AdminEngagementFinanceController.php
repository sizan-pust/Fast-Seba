<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminEngagementFinanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminEngagementFinanceController extends Controller
{
    public function __construct(
        private readonly AdminEngagementFinanceService $resources
    ) {
    }

    public function index(Request $request, string $module): View
    {
        return view(
            'admin.manage.index',
            $this->resources->index($module, $request)
        );
    }

    public function create(Request $request, string $module): View
    {
        return view(
            'admin.manage.form',
            $this->resources->form($module, $request)
        );
    }

    public function store(
        Request $request,
        string $module
    ): RedirectResponse {
        $model = $this->resources->save(
            $module,
            $request,
            Auth::guard('admin')->user()
        );

        return redirect()
            ->route(
                'admin.manage.'.$module.'.show',
                array_merge(
                    ['id' => $model->getKey()],
                    $request->only('view')
                )
            )
            ->with('success', 'Record created successfully.');
    }

    public function show(
        Request $request,
        string $id,
        string $module
    ): View {
        return view(
            'admin.manage.show',
            $this->resources->show(
                $module,
                (int) $id,
                $request
            )
        );
    }

    public function edit(
        Request $request,
        string $id,
        string $module
    ): View {
        return view(
            'admin.manage.form',
            $this->resources->form(
                $module,
                $request,
                (int) $id
            )
        );
    }

    public function update(
        Request $request,
        string $id,
        string $module
    ): RedirectResponse {
        $model = $this->resources->save(
            $module,
            $request,
            Auth::guard('admin')->user(),
            (int) $id
        );

        return redirect()
            ->route(
                'admin.manage.'.$module.'.show',
                array_merge(
                    ['id' => $model->getKey()],
                    $request->only('view')
                )
            )
            ->with('success', 'Record updated successfully.');
    }

    public function destroy(
        Request $request,
        string $id,
        string $module
    ): RedirectResponse {
        $this->resources->delete(
            $module,
            (int) $id,
            $request,
            Auth::guard('admin')->user()
        );

        return redirect()
            ->route(
                'admin.manage.'.$module.'.index',
                $request->only('view')
            )
            ->with('success', 'Record deleted successfully.');
    }

    public function action(
        Request $request,
        string $id,
        string $module
    ): RedirectResponse {
        $this->resources->action(
            $module,
            (int) $id,
            $request,
            Auth::guard('admin')->user()
        );

        return back()->with(
            'success',
            'Administrative action completed.'
        );
    }

    public function pageAction(
        Request $request,
        string $module
    ): RedirectResponse {
        $result = $this->resources->pageAction(
            $module,
            $request
        );

        return back()->with(
            'success',
            'Operation completed: '.json_encode($result)
        );
    }
}
