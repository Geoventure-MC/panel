<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ScheduledEvent;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminScheduledEventController extends Controller
{
    public function index()
    {
        $upcoming = ScheduledEvent::where('status', 'pending')
            ->orderBy('scheduled_at')
            ->limit(50)
            ->get();

        $past = ScheduledEvent::whereIn('status', ['fired', 'cancelled'])
            ->orderByDesc('fired_at')
            ->orderByDesc('scheduled_at')
            ->limit(20)
            ->get();

        return view('admin.scheduled-events.index', compact('upcoming', 'past'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'type'         => ['required', Rule::in(ScheduledEvent::TYPES)],
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string|max:1000',
            'scheduled_at' => 'required|date|after:now',
            'recurring'    => ['required', Rule::in(ScheduledEvent::RECURRINGS)],
        ]);

        $event = ScheduledEvent::create([
            'type'         => $request->type,
            'title'        => $request->title,
            'description'  => $request->description,
            'scheduled_at' => $request->scheduled_at,
            'recurring'    => $request->recurring,
            'status'       => 'pending',
        ]);

        AuditLog::record('scheduled_event.create', $event);

        return redirect()->route('admin.scheduled-events')->with('success', __('messages.flash.scheduled_event_created'));
    }

    public function cancel(ScheduledEvent $event)
    {
        if ($event->status === 'pending') {
            $event->update(['status' => 'cancelled']);
            AuditLog::record('scheduled_event.cancel', $event);
        }

        return redirect()->route('admin.scheduled-events')->with('success', __('messages.flash.scheduled_event_cancelled'));
    }

    public function destroy(ScheduledEvent $event)
    {
        $event->delete();
        AuditLog::record('scheduled_event.delete', $event);

        return redirect()->route('admin.scheduled-events')->with('success', __('messages.flash.scheduled_event_deleted'));
    }
}
