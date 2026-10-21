@extends('admin.moderation.layout')

@section('mod')
@php $state = $st['state']; @endphp
<div class="card shadow-sm border-0" id="mod-unavailable" data-state="{{ $state }}">
    <div class="card-body">
        <h4 class="mb-3"><i class="bi bi-database-exclamation me-2"></i>{{ __("moderation.unavailable.$state.title") }}</h4>
        <p>{{ __("moderation.unavailable.$state.body") }}</p>
        <ul class="small text-muted">
            <li>{{ __('moderation.unavailable.hint_env') }}</li>
            <li>{{ __('moderation.unavailable.hint_plugin') }}</li>
            <li>{{ __('moderation.unavailable.hint_readonly') }}</li>
        </ul>
        <details class="mt-3">
            <summary>{{ __('moderation.unavailable.contract') }}</summary>
<pre class="mt-2 p-3 bg-body-secondary rounded small">CREATE TABLE gf_sanctions (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  player_uuid VARCHAR(36) NOT NULL,
  player_name VARCHAR(32) NOT NULL,
  type VARCHAR(8) NOT NULL,          -- warn|mute|kick|ban
  reason VARCHAR(255) NULL,
  staff_name VARCHAR(32) NULL,
  staff_uuid VARCHAR(36) NULL,
  created_at BIGINT NOT NULL,        -- epoch (s)
  expires_at BIGINT NULL,            -- NULL = permanent
  active TINYINT(1) NOT NULL DEFAULT 1,
  revoked_by VARCHAR(32) NULL,
  revoked_at BIGINT NULL,
  revoke_reason VARCHAR(255) NULL,
  ip_hash VARCHAR(64) NULL,          -- jamais lu ni affiché par le panel
  KEY idx_player (player_uuid, created_at),
  KEY idx_created (created_at)
);
CREATE TABLE gf_reports (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  reporter_uuid VARCHAR(36) NOT NULL,
  reporter_name VARCHAR(32) NOT NULL,
  target_name VARCHAR(32) NOT NULL,
  reason VARCHAR(255) NULL,
  world VARCHAR(64) NULL, x DOUBLE NULL, y DOUBLE NULL, z DOUBLE NULL,
  context TEXT NULL,                 -- dernières lignes de chat
  status VARCHAR(8) NOT NULL DEFAULT 'open',   -- open|taken|closed
  handled_by VARCHAR(32) NULL,
  created_at BIGINT NOT NULL,
  closed_at BIGINT NULL,
  note VARCHAR(500) NULL,
  KEY idx_status (status, created_at),
  KEY idx_target (target_name)
);
CREATE TABLE gf_player_ips (          -- facultatif : « comptes liés » (super-admin)
  player_uuid VARCHAR(36) NOT NULL,
  ip_hash VARCHAR(64) NOT NULL,       -- jamais affiché ni exposé
  first_seen BIGINT NOT NULL,
  last_seen BIGINT NOT NULL,
  PRIMARY KEY (player_uuid, ip_hash),
  KEY idx_ip (ip_hash)
);</pre>
        </details>
    </div>
</div>
@endsection
