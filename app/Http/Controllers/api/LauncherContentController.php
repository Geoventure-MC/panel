<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\OptionsLauncherContent;

class LauncherContentController extends Controller
{
    public function getLauncherContent()
    {
        $items = OptionsLauncherContent::where('active', true)
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get();

        $grouped = [
            'news_banners' => $items->where('section', 'news_banner')->values()->map(fn ($item) => [
                'id'          => $item->id,
                'title'       => $item->title,
                'description' => $item->description,
                'icon'        => $item->icon,
                'imageUrl'    => $item->image_url,
                'url'         => $item->url,
                'sortOrder'   => $item->sort_order,
                'createdAt'   => $item->created_at->toIso8601String(),
            ]),
            'shortcuts' => $items->where('section', 'shortcut')->values()->map(fn ($item) => [
                'id'          => $item->id,
                'title'       => $item->title,
                'description' => $item->description,
                'icon'        => $item->icon,
                'imageUrl'    => $item->image_url,
                'url'         => $item->url,
                'sortOrder'   => $item->sort_order,
                'createdAt'   => $item->created_at->toIso8601String(),
            ]),
            'discover' => $items->where('section', 'discover')->values()->map(fn ($item) => [
                'id'          => $item->id,
                'title'       => $item->title,
                'description' => $item->description,
                'icon'        => $item->icon,
                'imageUrl'    => $item->image_url,
                'url'         => $item->url,
                'sortOrder'   => $item->sort_order,
                'createdAt'   => $item->created_at->toIso8601String(),
            ]),
        ];

        return response()->json($grouped, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
