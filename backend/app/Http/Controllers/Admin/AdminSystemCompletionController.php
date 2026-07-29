<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminSystemCompletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminSystemCompletionController extends Controller
{
    public function __construct(
        private readonly AdminSystemCompletionService $resources
    ) {
    }

    public function index(
        Request $request,
        string $module
    ): View {
        $data = $this->resources->index($module, $request);

        return view(
            ($data['healthMode'] ?? false)
                ? 'admin.system.health'
                : 'admin.manage.index',
            $data
        );
    }

    public function create(
        Request $request,
        string $module
    ): View {
        return view(
            'admin.system.form',
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
                'admin.system.'.$module.'.show',
                array_filter([
                    'id' => $model->getKey(),
                    'view' => $request->input('view'),
                ])
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
                $id,
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
            'admin.system.form',
            $this->resources->form(
                $module,
                $request,
                $id
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
            $id
        );

        return redirect()
            ->route(
                'admin.system.'.$module.'.show',
                array_filter([
                    'id' => $model->getKey(),
                    'view' => $request->input('view'),
                ])
            )
            ->with('success', 'Record updated successfully.');
    }

    public function destroy(
        Request $request,
        string $id,
        string $module
    ): RedirectResponse {
        $this->resources->destroy(
            $module,
            $id,
            $request,
            Auth::guard('admin')->user()
        );

        return redirect()
            ->route(
                'admin.system.'.$module.'.index',
                array_filter([
                    'view' => $request->input('view'),
                ])
            )
            ->with('success', 'Record deleted successfully.');
    }

    public function action(
        Request $request,
        string $id,
        string $module
    ): RedirectResponse {
        $result = $this->resources->action(
            $module,
            $id,
            $request,
            Auth::guard('admin')->user()
        );

        return back()->with(
            'success',
            'Operation completed: '.json_encode($result)
        );
    }

    public function pageAction(
        Request $request,
        string $module
    ): RedirectResponse {
        $result = $this->resources->pageAction(
            $module,
            $request,
            Auth::guard('admin')->user()
        );

        return back()->with(
            'success',
            'Operation completed: '.json_encode($result)
        );
    }
}
