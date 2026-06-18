<?php

namespace App\Http\Controllers\users;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminUserController extends Controller
{
    public function index()
    {
        $users = User::all();
        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        return view('admin.users.create');
    }

    public function add(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return redirect()->route('admin.users')->with('success', __('messages.flash.user_added'));
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $id,
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        $userData = [
            'name' => $request->name,
            'email' => $request->email,
        ];

        if ($request->filled('password')) {
            $userData['password'] = Hash::make($request->password);
        }

        $user->update($userData);

        return redirect()->route('admin.users')->with('success', __('messages.flash.user_updated'));
    }

    public function delete($id)
    {
        $user = User::findOrFail($id);

        // Empêche de supprimer son propre compte.
        if (auth()->id() === $user->id) {
            return redirect()->route('admin.users')
                ->with('error', __('messages.flash.user_delete_self'));
        }

        // Empêche de supprimer le dernier admin (sinon plus aucun accès admin).
        if ($user->is_admin && User::where('is_admin', true)->count() <= 1) {
            return redirect()->route('admin.users')
                ->with('error', __('messages.flash.user_delete_last_admin'));
        }

        $user->delete();
        AuditLog::record('user.delete', $user);

        return redirect()->route('admin.users')->with('success', __('messages.flash.user_deleted'));
    }
}
