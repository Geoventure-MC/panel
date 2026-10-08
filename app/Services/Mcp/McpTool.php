<?php

namespace App\Services\Mcp;

use Closure;

/**
 * Définition d'un outil MCP. `scope` = portée minimale de la clé (read|write|admin).
 * Tout outil write/admin exige `confirm: true` (ajouté automatiquement au schéma).
 */
class McpTool
{
    public function __construct(
        public string $name,
        public string $description,
        public string $scope,
        public array $properties,
        public array $required,
        public array $rules,
        public Closure $handler,
        public bool $destructive = false,
    ) {
    }

    public static function make(string $name, string $scope, string $description, array $properties = [], array $required = [], array $rules = [], ?Closure $handler = null, bool $destructive = false): self
    {
        return new self($name, $description, $scope, $properties, $required, $rules, $handler ?? fn () => [], $destructive);
    }

    public function needsConfirm(): bool
    {
        return $this->scope !== 'read';
    }

    public function level(): int
    {
        return ['read' => 1, 'write' => 2, 'admin' => 3][$this->scope];
    }

    /** Définition renvoyée par tools/list. */
    public function definition(): array
    {
        $props = $this->properties;
        $required = $this->required;
        if ($this->needsConfirm()) {
            $props['confirm'] = ['type' => 'boolean', 'description' => 'Doit valoir true pour exécuter l\'action (confirmation explicite).'];
            $required[] = 'confirm';
        }

        return [
            'name'        => $this->name,
            'description' => $this->description . ' [portée : ' . $this->scope . ($this->destructive ? ', destructif' : '') . ']',
            'inputSchema' => [
                'type'                 => 'object',
                'properties'           => (object) $props,
                'required'             => array_values($required),
                'additionalProperties' => false,
            ],
            'annotations' => [
                'readOnlyHint'    => $this->scope === 'read',
                'destructiveHint' => $this->destructive,
                'idempotentHint'  => $this->scope === 'read',
                'openWorldHint'   => false,
            ],
        ];
    }
}
