<?php

namespace App\Http\Controllers;

use App\Http\Controllers\api\ServerStatusController;
use Illuminate\Support\Facades\Log;

/**
 * Page de statut publique (/status) — partageable (Discord, etc.), sans auth.
 * N'utilise QUE les statuts en cache (aucun ping SLP bloquant) ; le JS de la
 * page se rafraîchit via l'endpoint public /utils/servers-status.
 */
class StatusPageController extends Controller
{
    public function index(ServerStatusController $statusController)
    {
        try {
            $statuses = $statusController->getServersStatusCached();
        } catch (\Throwable $e) {
            Log::warning('StatusPageController@index: ' . $e->getMessage());
            $statuses = [];
        }

        return view('status', ['statuses' => $statuses]);
    }
}
