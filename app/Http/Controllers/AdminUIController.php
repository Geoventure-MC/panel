<?php

namespace App\Http\Controllers;

use App\Models\OptionsUI;
use Illuminate\Http\Request;

class AdminUIController extends Controller
{
    public function show()
    {
        $uiOptions = OptionsUI::first();
        if (!$uiOptions) {
            $uiOptions = OptionsUI::create([
                'alert_activation' => 1,
                'alert_scroll' => 0,
                'alert_msg'=>'Bienvenue sur le launcher',
                'video_activation'=> 0,
                'video_url'=>'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'splash'=>'Ceci est du code',
                'splash_author'=>'Riptiaz',
                'accent_color'=>'#FFA500',
            ]);
        }

        return view('admin.ui', compact('uiOptions'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'alert_activation' => 'boolean',
            'alert_scroll' => 'boolean',
            'alert_msg'=>'required|string|max:255',
            'video_activation'=> 'boolean',
            'video_url'=>'required|string|max:255|starts_with:https://,http://',
            'splash'=>'required|string|max:255',
            'splash_author'=>'required|string|max:255',
            'accent_color'=>['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $uiOptions = OptionsUI::first();

        if ($uiOptions) {
            // Seuls les champs validés (et non toute la requête) sont écrits.
            $uiOptions->update($validated);
        }

        return redirect()->route('admin.ui')->with('success', __('messages.flash.ui_updated'));
    }
}
