<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CommunityMod;
use Illuminate\Http\Request;

class AdminCommunityModController extends Controller
{
    public function index()
    {
        $mods = CommunityMod::orderByDesc('created_at')->get();
        return view('admin.community-mods', compact('mods'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'filename'    => 'required|string|max:255',
            'url'         => 'required|url|max:500',
            'icon'        => 'nullable|url|max:500',
        ]);

        $mod = CommunityMod::create([
            'name'        => $request->name,
            'description' => $request->description,
            'filename'    => $request->filename,
            'url'         => $request->url,
            'icon'        => $request->icon,
            'active'      => true,
        ]);

        AuditLog::record('community_mod.create', $mod);

        return redirect()->route('admin.community-mods')->with('success', __('messages.flash.community_mod_created'));
    }

    public function update(Request $request, CommunityMod $mod)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'filename'    => 'required|string|max:255',
            'url'         => 'required|url|max:500',
            'icon'        => 'nullable|url|max:500',
        ]);

        $mod->update([
            'name'        => $request->name,
            'description' => $request->description,
            'filename'    => $request->filename,
            'url'         => $request->url,
            'icon'        => $request->icon,
        ]);

        AuditLog::record('community_mod.update', $mod);

        return redirect()->route('admin.community-mods')->with('success', __('messages.flash.community_mod_updated'));
    }

    public function toggle(CommunityMod $mod)
    {
        $mod->update(['active' => !$mod->active]);
        AuditLog::record('community_mod.toggle', $mod);

        return redirect()->route('admin.community-mods')->with('success', __('messages.flash.community_mod_toggled'));
    }

    public function destroy(CommunityMod $mod)
    {
        AuditLog::record('community_mod.delete', $mod);
        $mod->delete();

        return redirect()->route('admin.community-mods')->with('success', __('messages.flash.community_mod_deleted'));
    }
}
