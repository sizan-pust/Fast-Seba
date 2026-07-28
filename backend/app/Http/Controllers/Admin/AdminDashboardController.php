<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __construct(
        private readonly AdminDashboardService $dashboard
    ) {
    }

    public function index(
        Request $request
    ): View {
        $zoneId = $request->integer('zone_id');

        if ($zoneId <= 0) {
            $zoneId = null;
        }

        return view(
            'admin.dashboard',
            $this->dashboard->overview($zoneId)
        );
    }
}
