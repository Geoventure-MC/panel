<?php

return [
    'sidebar' => 'Versions',
    'title' => 'Component versions',
    'header' => 'Component versions',
    'intro' => 'Version actually deployed against the latest GitHub release. Nothing is guessed: without a reliable source the component is "unknown".',
    'component' => 'Component',
    'running' => 'Deployed',
    'latest' => 'Latest release',
    'status' => 'Status',
    'source' => 'Source',
    'release' => 'Release',
    'refresh' => 'Reload from GitHub',
    'generated_at' => 'Computed on :date (releases cached for 10 min)',
    'stale' => 'last known value',
    'status_labels' => [
        'uptodate' => 'Up to date',
        'behind' => 'Behind',
        'unknown' => 'Unknown',
    ],
    'sources' => [
        'modpack' => 'Launcher modpack jar',
        'telemetry' => 'Launcher telemetry (14 days, :users distinct player(s))',
        'panel' => 'Panel version',
        'reported' => 'Manual report (:date)',
        'reported_nodate' => 'Manual report',
        'none_modpack' => 'No matching jar in the modpack',
        'none_telemetry' => 'No recent telemetry',
        'none_panel' => 'Panel version unreadable',
        'none_reported' => 'No source: fill in the versions file',
    ],
    'reasons' => [
        'no_source' => 'Deployed version unknown.',
        'no_access' => 'Repository not accessible (private? token missing or insufficient).',
        'rate_limited' => 'GitHub rate limit reached (a token raises it).',
        'unreachable' => 'GitHub unreachable.',
        'error' => 'Unusable GitHub response.',
        'no_release' => 'No release published.',
    ],
    'token' => [
        'present' => 'GitHub token configured.',
        'absent' => 'No GitHub token (GEO_GITHUB_TOKEN): public API limited to 60 requests per hour, public repositories only.',
    ],
    'flash' => [
        'refreshed' => 'Release cache cleared, data reloaded.',
    ],
];
