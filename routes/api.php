<?php

use App\Http\Controllers\api\McpController;
use Illuminate\Support\Facades\Route;

// Les routes API publiques du launcher sont dans web.php sous le préfixe /utils.

// API MCP : sans session ni CSRF (routes/api.php => groupe « api »), authentifiée par clé Bearer.
// Désactivée par défaut (Admin → API MCP). Le débit est géré dans le contrôleur (par IP puis par clé).
Route::post('/mcp', [McpController::class, 'handle'])->name('mcp.handle');
Route::match(['get', 'delete'], '/mcp', [McpController::class, 'notAllowed']);
