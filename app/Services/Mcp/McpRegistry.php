<?php

namespace App\Services\Mcp;

use App\Services\Mcp\Tools\AdminTools;
use App\Services\Mcp\Tools\ReadTools;
use App\Services\Mcp\Tools\WriteTools;

class McpRegistry
{
    /** @return array<string,McpTool> indexés par nom */
    public static function all(): array
    {
        $out = [];
        foreach ([ReadTools::all(), WriteTools::all(), AdminTools::all()] as $group) {
            foreach ($group as $tool) {
                $out[$tool->name] = $tool;
            }
        }

        return $out;
    }
}
