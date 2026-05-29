<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\OptionsNotification;
use Illuminate\Support\Carbon;

class NotificationController extends Controller
{
    public function getNotifications()
    {
        $notifications = OptionsNotification::where('active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', Carbon::now());
            })
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($n) => [
                'id'        => $n->id,
                'type'      => $n->type,
                'message'   => $n->message,
                'url'       => $n->url,
                'expiresAt' => $n->expires_at?->toIso8601String(),
                'createdAt' => $n->created_at->toIso8601String(),
            ]);

        return response()->json($notifications, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
