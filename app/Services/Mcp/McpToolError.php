<?php

namespace App\Services\Mcp;

/** Erreur « métier » d'un outil : renvoyée au client avec isError = true (message sûr, en français). */
class McpToolError extends \RuntimeException
{
}
