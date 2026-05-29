<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\OptionsNotification;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    public function index()
    {
        $notifications = OptionsNotification::orderByDesc('created_at')->get();
        return view('admin.notifications', compact('notifications'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'type'       => 'required|in:info,warning,maintenance,event',
            'message'    => 'required|string|max:500',
            'url'        => 'nullable|url|max:255',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $notification = OptionsNotification::create([
            'type'       => $request->type,
            'message'    => $request->message,
            'url'        => $request->url,
            'active'     => true,
            'expires_at' => $request->expires_at,
        ]);

        AuditLog::record('notification.create', $notification);

        return redirect()->route('admin.notifications')->with('success', __('messages.flash.notification_created'));
    }

    public function toggle(OptionsNotification $notification)
    {
        $notification->update(['active' => !$notification->active]);
        AuditLog::record('notification.toggle', $notification);

        return redirect()->route('admin.notifications')->with('success', __('messages.flash.notification_toggled'));
    }

    public function destroy(OptionsNotification $notification)
    {
        AuditLog::record('notification.delete', $notification);
        $notification->delete();

        return redirect()->route('admin.notifications')->with('success', __('messages.flash.notification_deleted'));
    }
}
