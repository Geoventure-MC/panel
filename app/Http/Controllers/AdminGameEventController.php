<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\GameEvent;
use Illuminate\Http\Request;

class AdminGameEventController extends Controller
{
    public function index()
    {
        return view('admin.game_events', ['events' => GameEvent::orderBy('id')->get()]);
    }

    public function update(Request $request, GameEvent $event)
    {
        $request->validate([
            'enabled' => 'nullable|boolean', 'announce' => 'nullable|boolean',
            'opens_at' => 'nullable|date', 'ends_at' => 'nullable|date|after:opens_at',
            'settings' => 'nullable|array', 'rewards' => 'nullable|array|size:4',
        ]);
        $tz = 'Europe/Paris';
        $in = (array) $request->input('settings', []);
        if ($request->filled('rewards')) {
            $in['rewards'] = $request->input('rewards');
        }
        $before = $event->only(['enabled', 'opens_at', 'ends_at', 'settings']);
        $event->update([
            'enabled' => $request->boolean('enabled'),
            'announce' => $request->boolean('announce'),
            'opens_at' => $request->filled('opens_at') ? \Illuminate\Support\Carbon::parse($request->opens_at, $tz)->utc() : null,
            'ends_at' => $request->filled('ends_at') ? \Illuminate\Support\Carbon::parse($request->ends_at, $tz)->utc() : null,
            'settings' => GameEvent::cleanSettings($event->slug, $in) + ($event->settings ?? []),
        ]);
        $event->syncNotification();
        AuditLog::record('game_event.update', $event);

        return redirect()->route('admin.game-events')->with('success', __('messages.flash.game_event_saved'));
    }
}
