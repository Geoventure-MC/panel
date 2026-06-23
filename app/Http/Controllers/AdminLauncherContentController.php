<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\OptionsLauncherContent;
use Illuminate\Http\Request;

class AdminLauncherContentController extends Controller
{
    public function index()
    {
        $items = OptionsLauncherContent::orderBy('sort_order')->orderByDesc('created_at')->get();
        $newsBanners = $items->where('section', 'news_banner')->values();
        $shortcuts   = $items->where('section', 'shortcut')->values();
        $discover    = $items->where('section', 'discover')->values();

        return view('admin.launcher-content', compact('newsBanners', 'shortcuts', 'discover'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'section'    => 'required|in:news_banner,shortcut,discover',
            'title'      => 'required|string|max:255',
            'description'=> 'nullable|string',
            'icon'       => 'nullable|string|max:255',
            'image_url'  => 'nullable|url|max:255',
            'url'        => 'nullable|url|max:255',
            'sort_order' => 'nullable|integer',
        ]);

        $item = OptionsLauncherContent::create([
            'section'     => $request->section,
            'title'       => $request->title,
            'description' => $request->description,
            'icon'        => $request->icon,
            'image_url'   => $request->image_url,
            'url'         => $request->url,
            'sort_order'  => $request->sort_order ?? 0,
            'active'      => true,
        ]);

        AuditLog::record('launcher_content.create', $item);

        return redirect()->route('admin.launcher-content')->with('success', __('messages.flash.launcher_content_created'));
    }

    public function update(Request $request, $id)
    {
        $item = OptionsLauncherContent::findOrFail($id);

        $request->validate([
            'section'    => 'required|in:news_banner,shortcut,discover',
            'title'      => 'required|string|max:255',
            'description'=> 'nullable|string',
            'icon'       => 'nullable|string|max:255',
            'image_url'  => 'nullable|url|max:255',
            'url'        => 'nullable|url|max:255',
            'sort_order' => 'nullable|integer',
        ]);

        $item->update([
            'section'     => $request->section,
            'title'       => $request->title,
            'description' => $request->description,
            'icon'        => $request->icon,
            'image_url'   => $request->image_url,
            'url'         => $request->url,
            'sort_order'  => $request->sort_order ?? 0,
        ]);

        AuditLog::record('launcher_content.update', $item);

        return redirect()->route('admin.launcher-content')->with('success', __('messages.flash.launcher_content_updated'));
    }

    public function toggle($id)
    {
        $item = OptionsLauncherContent::findOrFail($id);
        $item->update(['active' => !$item->active]);
        AuditLog::record('launcher_content.toggle', $item);

        return redirect()->route('admin.launcher-content')->with('success', __('messages.flash.launcher_content_toggled'));
    }

    public function destroy($id)
    {
        $item = OptionsLauncherContent::findOrFail($id);
        AuditLog::record('launcher_content.delete', $item);
        $item->delete();

        return redirect()->route('admin.launcher-content')->with('success', __('messages.flash.launcher_content_deleted'));
    }
}
