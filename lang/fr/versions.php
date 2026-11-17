<?php

return [
    'sidebar' => 'Versions',
    'title' => 'Versions des composants',
    'header' => 'Versions des composants',
    'intro' => "Version réellement en place face à la dernière release GitHub. Rien n'est deviné : sans source fiable, le composant est « inconnu ».",
    'component' => 'Composant',
    'running' => 'En place',
    'latest' => 'Dernière release',
    'status' => 'État',
    'source' => 'Source',
    'release' => 'Release',
    'refresh' => 'Relire GitHub',
    'generated_at' => 'Calculé le :date (releases en cache 10 min)',
    'stale' => 'dernière valeur connue',
    'status_labels' => [
        'uptodate' => 'À jour',
        'behind' => 'En retard',
        'unknown' => 'Inconnu',
    ],
    'sources' => [
        'modpack' => 'Jar du modpack du launcher',
        'telemetry' => 'Télémétrie du launcher (14 jours, :users joueur(s) distinct(s))',
        'panel' => 'Version du panel',
        'reported' => 'Relevé manuel (:date)',
        'reported_nodate' => 'Relevé manuel',
        'none_modpack' => 'Aucun jar correspondant dans le modpack',
        'none_telemetry' => 'Aucune télémétrie récente',
        'none_panel' => 'Version du panel illisible',
        'none_reported' => 'Aucune source : à renseigner dans le fichier de versions',
    ],
    'reasons' => [
        'no_source' => 'Version en place inconnue.',
        'no_access' => 'Dépôt inaccessible (privé ? jeton absent ou insuffisant).',
        'rate_limited' => 'Limite de requêtes GitHub atteinte (un jeton la relève).',
        'unreachable' => 'GitHub injoignable.',
        'error' => 'Réponse GitHub inexploitable.',
        'no_release' => 'Aucune release publiée.',
    ],
    'token' => [
        'present' => 'Jeton GitHub configuré.',
        'absent' => 'Aucun jeton GitHub (GEO_GITHUB_TOKEN) : API publique limitée à 60 requêtes par heure, dépôts publics seulement.',
    ],
    'flash' => [
        'refreshed' => 'Cache des releases vidé, données relues.',
    ],
];
