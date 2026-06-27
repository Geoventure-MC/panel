<?php

namespace App\Http\Controllers;

use App\Models\Achievement;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminAchievementController extends Controller
{
    /**
     * Vocabulaire des conditions partagé avec le launcher (contrat).
     * Pour launch_count / playtime_hours / instances_tried, condition_value = seuil N.
     */
    private const CONDITION_TYPES = [
        'first_launch', 'launch_count', 'playtime_hours', 'instances_tried', 'manual',
    ];

    public function index()
    {
        $achievements = Achievement::orderBy('category')->orderByDesc('created_at')->get();
        return view('admin.achievements.index', compact('achievements'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'code'            => 'required|string|max:255|unique:achievements,code',
            'name'            => 'required|string|max:255',
            'description'     => 'nullable|string|max:1000',
            'icon'            => 'nullable|string|max:255',
            'points'          => 'required|integer|min:0|max:100000',
            'category'        => 'nullable|string|max:255',
            'condition_type'  => ['required', Rule::in(self::CONDITION_TYPES)],
            'condition_value' => 'nullable|integer|min:0',
        ]);

        $achievement = Achievement::create([
            'code'            => $request->code,
            'name'            => $request->name,
            'description'     => $request->description,
            'icon'            => $request->icon,
            'points'          => $request->points,
            'category'        => $request->category,
            'condition_type'  => $request->condition_type,
            'condition_value' => $request->condition_value,
            'active'          => true,
        ]);

        AuditLog::record('achievement.create', $achievement);

        return redirect()->route('admin.achievements')->with('success', __('messages.flash.achievement_created'));
    }

    public function update(Request $request, Achievement $achievement)
    {
        $request->validate([
            'code'            => ['required', 'string', 'max:255', Rule::unique('achievements', 'code')->ignore($achievement->id)],
            'name'            => 'required|string|max:255',
            'description'     => 'nullable|string|max:1000',
            'icon'            => 'nullable|string|max:255',
            'points'          => 'required|integer|min:0|max:100000',
            'category'        => 'nullable|string|max:255',
            'condition_type'  => ['required', Rule::in(self::CONDITION_TYPES)],
            'condition_value' => 'nullable|integer|min:0',
        ]);

        $achievement->update([
            'code'            => $request->code,
            'name'            => $request->name,
            'description'     => $request->description,
            'icon'            => $request->icon,
            'points'          => $request->points,
            'category'        => $request->category,
            'condition_type'  => $request->condition_type,
            'condition_value' => $request->condition_value,
        ]);

        AuditLog::record('achievement.update', $achievement);

        return redirect()->route('admin.achievements')->with('success', __('messages.flash.achievement_updated'));
    }

    public function toggle(Achievement $achievement)
    {
        $achievement->update(['active' => !$achievement->active]);
        AuditLog::record('achievement.toggle', $achievement);

        return redirect()->route('admin.achievements')->with('success', __('messages.flash.achievement_toggled'));
    }

    public function destroy(Achievement $achievement)
    {
        AuditLog::record('achievement.delete', $achievement);
        $achievement->delete();

        return redirect()->route('admin.achievements')->with('success', __('messages.flash.achievement_deleted'));
    }
}
