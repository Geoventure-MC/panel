@extends('admin.analytics.layout')

@section('an')
@php $state = $st['state']; @endphp
<div class="card shadow-sm border-0" id="an-unavailable" data-state="{{ $state }}">
    <div class="card-body">
        <h4 class="mb-3"><i class="bi bi-database-exclamation me-2"></i>{{ __("analytics.unavailable.$state.title") }}</h4>
        <p>{{ __("analytics.unavailable.$state.body") }}</p>
        <ul class="small text-muted">
            <li>{{ __('analytics.unavailable.hint_env') }}</li>
            <li>{{ __('analytics.unavailable.hint_plugin') }}</li>
            <li>{{ __('analytics.unavailable.hint_readonly') }}</li>
        </ul>
        <details class="mt-3">
            <summary>{{ __('analytics.unavailable.contract') }}</summary>
<pre class="an-mono mt-2 p-3 bg-body-secondary rounded">CREATE TABLE gf_metrics (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  ts BIGINT NOT NULL,               -- epoch (s)
  scope VARCHAR(16) NOT NULL,       -- server|faction|economy|oil|pollution|players|missiles|war|usage
  ref VARCHAR(64) NOT NULL DEFAULT '',
  metric VARCHAR(48) NOT NULL,
  value DOUBLE NOT NULL,
  extra VARCHAR(255) NULL,
  KEY idx_metric (scope, metric, ref, ts),
  KEY idx_ts (ts)
);
CREATE TABLE gf_events (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  ts BIGINT NOT NULL,
  type VARCHAR(32) NOT NULL,
  ref VARCHAR(64) NOT NULL,
  actor VARCHAR(64) NULL,
  data TEXT NULL,                   -- JSON
  KEY idx_type_ts (type, ts),
  KEY idx_ref_ts (ref, ts)
);
CREATE TABLE gf_rollup LIKE gf_metrics;  -- moyennes horaires / journalières</pre>
        </details>
    </div>
</div>
@endsection
