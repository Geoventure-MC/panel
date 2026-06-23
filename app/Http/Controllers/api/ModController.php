<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\OptionsMods;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class ModController extends Controller
{
    public function getMods(): JsonResponse
    {
        // Multi-instance : ?instance=<slug> renvoie les mods de cette instance
        // PLUS les mods partagés (instance NULL). Sans paramètre → tous les mods
        // (comportement global historique, rétrocompatible).
        $slug = request()->query('instance');
        if ($slug) {
            $mods = OptionsMods::where('instance', $slug)
                ->orWhereNull('instance')
                ->get();
        } else {
            $mods = OptionsMods::all();
        }

        $modsData = [];
        $optionalMods = [];

        foreach ($mods as $mod) {
            $modsFile = basename($mod->file);
            $modsIcon = !empty($mod->icon) ? $this->getAbsoluteUrl($mod->icon) : "";

            $modsData[$modsFile] = [
                "name" => $mod->name,
                "description" => $mod->description,
                "icon" => $modsIcon,
                "recommanded" => (bool)$mod->recommended
            ];

            if ($mod->optional) {
                $optionalMods[] = $modsFile;
            }
        }

        $output = [
            "optionalMods" => $optionalMods,
            "mods" => $modsData
        ];

        return response()->json($output, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function getAbsoluteUrl($imagePath)
    {
        return asset('storage/' . $imagePath);
    }
}




