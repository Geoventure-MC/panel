<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\api\ServerStatusController;
use App\Models\OptionsSecurity;
use App\Models\OptionsNotification;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class DashboardController extends Controller
{
    public function index()
    {
        $userCount = User::count();
        $releases = $this->getReleases();
        $security = OptionsSecurity::first();
        $maintenanceActive = $security ? (bool) $security->maintenance : false;

        $serverStatuses = $this->getServerStatuses();
        $activeNotifications = $this->getActiveNotifications();

        return view('admin.index', compact(
            'userCount', 'releases', 'maintenanceActive',
            'serverStatuses', 'activeNotifications'
        ));
    }

    private function getServerStatuses()
    {
        try {
            $controller = new ServerStatusController();
            $response = $controller->getServersStatus();
            return json_decode($response->getContent(), true) ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getActiveNotifications()
    {
        try {
            return OptionsNotification::where('active', true)
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
                })
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getReleases()
    {
        try {
            $response = Http::get('https://github.com/Geoventure-MC/panel/releases.atom');

            if (!$response->successful()) {
                return [];
            }

            $xml = simplexml_load_string($response->body());
            $releases = [];

            foreach ($xml->entry as $entry) {
                $releases[] = (object) [
                    'title'       => (string) $entry->title,
                    'description' => strip_tags((string) $entry->content),
                    'date'        => date('d/m/Y H:i', strtotime((string) $entry->updated)),
                    'author'      => (string) $entry->author->name,
                    'link'        => (string) $entry->link['href'],
                ];
            }

            return $releases;
        } catch (\Exception $e) {
            \Log::error('Erreur lors de la récupération des releases: ' . $e->getMessage());
            return [];
        }
    }
}
