<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AdminAuditController extends Controller
{
    public function index(Request $request)
    {
        $action = $request->query('action');
        $userId = $request->query('user_id');

        $logs = AuditLog::with('user')
            ->when($action, fn($q) => $q->where('action', $action))
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        // Listes pour les filtres (distinctes).
        $actions = AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action');
        $users = User::orderBy('name')->get(['id', 'name']);

        return view('admin.audit', compact('logs', 'actions', 'users', 'action', 'userId'));
    }
}
