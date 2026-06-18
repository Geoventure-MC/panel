<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\OptionsSecurity;
use Illuminate\Http\Request;

class AdminSecurityController extends Controller
{
    public function show()
    {
        $securityOptions = OptionsSecurity::first();
        if (!$securityOptions) {
            $securityOptions = OptionsSecurity::create([
                'maintenance'         => 0,
                'maintenance_message' => 'Maintenance en cours.',
            ]);
        }

        return view('admin.security', compact('securityOptions'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'maintenance'         => 'boolean',
            'maintenance_message' => 'required|string|max:255',
        ]);

        $securityOptions = OptionsSecurity::first();

        if ($securityOptions) {
            // Seuls les champs validés sont écrits (pas de mass-assignment libre).
            $securityOptions->update($validated);
            AuditLog::record('security.update', $securityOptions);
        }

        return redirect()->route('admin.security')->with('success', __('messages.flash.security_updated'));
    }

    public function toggleMaintenance(Request $request)
    {
        $security = OptionsSecurity::first();
        if (!$security) {
            $security = OptionsSecurity::create([
                'maintenance'         => 0,
                'maintenance_message' => 'Maintenance en cours.',
            ]);
        }

        $newState = !$security->maintenance;
        $update = ['maintenance' => $newState];

        // Permet d'éditer le message au moment du basculement.
        if ($request->filled('maintenance_message')) {
            $validated = $request->validate([
                'maintenance_message' => 'string|max:255',
            ]);
            $update['maintenance_message'] = $validated['maintenance_message'];
        }

        $security->update($update);
        AuditLog::record('security.maintenance.toggle', $security, ['maintenance' => $newState]);

        $flash = $newState ? 'maintenance_enabled' : 'maintenance_disabled';

        return redirect()->back()->with('success', __('messages.flash.' . $flash));
    }
}
