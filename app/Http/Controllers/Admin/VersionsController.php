<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\VersionDashboard;

class VersionsController extends Controller
{
    public function index(VersionDashboard $versions)
    {
        $rows = $versions->rows();

        return view('admin.versions', [
            'rows' => $rows,
            'summary' => VersionDashboard::summary($rows),
            'hasToken' => VersionDashboard::hasToken(),
            'generatedAt' => now(),
        ]);
    }

    /** Vide le cache GitHub (10 min) pour relire les releases tout de suite. */
    public function refresh(VersionDashboard $versions)
    {
        $versions->forgetCache();

        return redirect()->route('admin.versions')->with('success', __('versions.flash.refreshed'));
    }
}
