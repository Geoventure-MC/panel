<?php

namespace App\Services\Mcp;

/** Erreur de protocole JSON-RPC (distincte d'une erreur d'outil, qui reste un résultat isError). */
class McpProtocolError extends \RuntimeException
{
    public function __construct(int $code, string $message)
    {
        parent::__construct($message, $code);
    }
}
