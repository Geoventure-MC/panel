<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\CommunityMod;

class CommunityModController extends Controller
{
    public function getCommunityMods()
    {
        $mods = CommunityMod::where('active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($m) => [
                'id'          => $m->id,
                'name'        => $m->name,
                'description' => $m->description,
                'icon'        => $m->icon,
                'filename'    => $m->filename,
                'url'         => $m->url,
            ]);

        return response()->json($mods, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
