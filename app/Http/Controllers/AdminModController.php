<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OptionsMods;
use Illuminate\Support\Facades\Storage;

class AdminModController extends Controller
{
    public function index(Request $request)
    {
        $modsDir = storage_path('app/public/data/mods');

        // glob() peut renvoyer false (dossier absent / erreur) → foreach(false)
        // déclencherait une TypeError. On retombe sur un tableau vide.
        $jarFiles = glob($modsDir . '/*.jar') ?: [];
        $modsData = [];

        foreach ($jarFiles as $index => $jarFile) {
            $modsData[] = [
                'file' => basename($jarFile),
                'name' => basename($jarFile),
                'description' => '',
                'icon' => '',
                'optional' => 0,
            ];
        }
        $optionalMods = OptionsMods::where('optional', 1)->get();
        $selectedModId = $request->input('selectedMod', null);

        return view('admin.mods', compact('modsData', 'optionalMods', 'selectedModId'));
    }


    public function updateOptionalMod(Request $request)
    {
        $request->validate([
            'mod_id'               => 'required|integer|exists:mods,id',
            'optional_name'        => 'required|string|max:150',
            'optional_description' => 'nullable|string|max:1000',
            'optional_recommended' => 'nullable|boolean',
            'optional_image'       => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
        ]);

        $mod = OptionsMods::findOrFail($request->mod_id);
        $mod->name = $request->optional_name;
        $mod->description = $request->optional_description;
        $mod->recommended = $request->has('optional_recommended') ? 1 : 0;

        if ($request->hasFile('optional_image')) {
            if ($mod->icon && Storage::disk('public')->exists($mod->icon)) {
                Storage::disk('public')->delete($mod->icon);
            }
            $mod->icon = $request->file('optional_image')->store('mod_icon', 'public');
        }

        $mod->save();

        return redirect()->back()->with('success', __('messages.flash.mod_updated'));
    }
    public function deleteOptionalMod($id)
    {
        try {
            $mod = OptionsMods::findOrFail($id);
            $mod->delete();

            return redirect()->back()->with('success', __('messages.flash.mod_deleted'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('messages.flash.mod_delete_error') . ' ' . $e->getMessage());
        }
    }

    public function addOptionalMod(Request $request)
    {
        $request->validate([
            'file'        => 'required|string|max:255',
            'name'        => 'required|string|max:150',
            'description' => 'nullable|string|max:1000',
        ]);

        $mod = new OptionsMods();
        $mod->file = $request->file;
        $mod->name = $request->name;
        $mod->description = $request->description;
        $mod->optional = 1;
        $mod->save();

        return redirect()->back()->with('success', __('messages.flash.mod_added'));
    }
    public function getOptionalModDetails($id)
    {
        $mod = OptionsMods::find($id);
        if (!$mod) {
            return response()->json(['error' => __('messages.flash.mod_not_found')], 404);
        }
        return response()->json($mod);
    }


}
