<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OptionsLoader;
use Illuminate\Support\Facades\Http;

class AdminLoaderController extends Controller
{
    public function index()
    {
        $row = OptionsLoader::first();

        return view('admin.loader', compact('row'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'minecraft_version' => 'required|string',
            'loader_activation' => 'required|boolean',
            'loader_type' => 'required|string',
            'loader_forge_version' => 'nullable|string',
            'loader_build_version' => 'nullable|string',
        ]);

        $optionsLoader = OptionsLoader::first();
        if (!$optionsLoader) {
            $optionsLoader = new OptionsLoader();
        }

        // Seuls les champs validés sont écrits (pas de mass-assignment libre).
        $optionsLoader->fill($validated);
        $optionsLoader->save();

        return redirect()->back()->with('success', __('messages.flash.loader_updated'));
    }

    public function getForgeBuilds(Request $request)
    {
        $mcVersion = $request->query('mc_version');
        $url = "https://files.minecraftforge.net/net/minecraftforge/forge/index_$mcVersion.html";
        $response = Http::get($url);
        $builds = [];

        if ($response->successful()) {
            $html = $response->body();
            $dom = new \DOMDocument;

            libxml_use_internal_errors(true);
            $dom->loadHTML($html);
            libxml_clear_errors();

            $xpath = new \DOMXPath($dom);

            $links = $xpath->query('//a[contains(@href, "maven.minecraftforge.net/net/minecraftforge/forge/")]');

            foreach ($links as $link) {
                $href = $link->getAttribute('href');

                if (preg_match('/forge\/([\d\.\-]+)\/forge-\1-/', $href, $matches)) {
                    $version = $matches[1];

                    if (!in_array($version, $builds)) {
                        $builds[] = $version;
                    }
                }
            }
        }

        return response()->json(['builds' => $builds]);
    }

    public function getFabricVersions()
    {
        $url = 'https://meta.fabricmc.net/v2/versions/loader';
        $response = Http::get($url);
        $versions = [];

        if ($response->successful()) {
            $versions = $response->json();
        }

        return response()->json(['versions' => $versions]);
    }
}