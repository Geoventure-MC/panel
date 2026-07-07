<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Changelog;
use Illuminate\Http\Request;

class AdminChangelogController extends Controller
{
    public function index()
    {
        $changelogs = Changelog::orderByDesc('published_at')->orderByDesc('id')->get();
        return view('admin.changelog', compact('changelogs'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'version'      => 'nullable|string|max:50',
            'title'        => 'required|string|max:255',
            'body'         => 'required|string|max:10000',
            'image_url'    => 'nullable|url|max:255',
            'published_at' => 'nullable|date',
        ]);

        $changelog = Changelog::create([
            'version'      => $request->version,
            'title'        => $request->title,
            'body'         => $request->body,
            'image_url'    => $request->image_url,
            'active'       => true,
            'published_at' => $request->published_at ?: now(),
        ]);

        AuditLog::record('changelog.create', $changelog);

        return redirect()->route('admin.changelog')->with('success', __('messages.flash.changelog_created'));
    }

    public function update(Request $request, Changelog $changelog)
    {
        $request->validate([
            'version'      => 'nullable|string|max:50',
            'title'        => 'required|string|max:255',
            'body'         => 'required|string|max:10000',
            'image_url'    => 'nullable|url|max:255',
            'published_at' => 'nullable|date',
        ]);

        $changelog->update([
            'version'      => $request->version,
            'title'        => $request->title,
            'body'         => $request->body,
            'image_url'    => $request->image_url,
            'published_at' => $request->published_at ?: $changelog->published_at,
        ]);

        AuditLog::record('changelog.update', $changelog);

        return redirect()->route('admin.changelog')->with('success', __('messages.flash.changelog_updated'));
    }

    public function toggle(Changelog $changelog)
    {
        $changelog->update(['active' => !$changelog->active]);
        AuditLog::record('changelog.toggle', $changelog);

        return redirect()->route('admin.changelog')->with('success', __('messages.flash.changelog_toggled'));
    }

    public function destroy(Changelog $changelog)
    {
        AuditLog::record('changelog.delete', $changelog);
        $changelog->delete();

        return redirect()->route('admin.changelog')->with('success', __('messages.flash.changelog_deleted'));
    }
}
