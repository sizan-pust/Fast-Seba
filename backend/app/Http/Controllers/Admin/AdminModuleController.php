<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdminModuleController extends Controller
{
    public function show(string $module): View
    {
        $item = $this->findModule($module);

        if (! $item) {
            throw new NotFoundHttpException();
        }

        return view(
            'admin.module-placeholder',
            [
                'module' => $module,
                'item' => $item,
            ]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findModule(
        string $module
    ): ?array {
        $sections = collect(
            config('admin_menu.sections', [])
        );

        return $sections
            ->flatMap(
                fn (array $section): Collection =>
                    collect($section['items'] ?? [])
            )
            ->first(
                fn (array $item): bool =>
                    ($item['module'] ?? null)
                    === $module
            );
    }
}
