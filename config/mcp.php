<?php

return [
    // Version du protocole MCP annoncée à l'initialisation.
    'protocol_version' => '2025-03-26',

    // Limitation de débit : par clé (après authentification) et par IP (avant, toutes requêtes).
    'rate_per_minute'    => (int) env('MCP_RATE_PER_MINUTE', 60),
    'ip_rate_per_minute' => (int) env('MCP_IP_RATE_PER_MINUTE', 300),

    // Blocage après échecs d'authentification : N échecs en `failure_window` s => blocage `lockout` s.
    'max_failures'   => (int) env('MCP_MAX_FAILURES', 8),
    'failure_window' => 600,
    'lockout'        => 900,

    // Taille maximale du corps d'une requête (octets).
    'max_body_bytes' => 65536,

    // Plafonds du montant par type de commande de jeu (outil game_command_send).
    'game_command_caps' => [
        'give_coins'          => 10000,
        'give_key'            => 10,
        'season_points'       => 1000,
        'bank_deposit'        => 100000,
        'broadcast'           => 0,
        'trigger_event'       => 0,
        'architecture_rating' => 10,
    ],
    // Nombre maximum de commandes de jeu envoyées par heure, par clé.
    'game_commands_per_hour' => 30,
];
