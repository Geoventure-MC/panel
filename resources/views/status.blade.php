<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('messages.status.title') }} — Geoventure</title>
    <style>
        :root { --bg:#0d1117; --card:#161d27; --border:rgba(125,135,150,.18); --text:#e6edf3; --muted:#8b97a7; --green:#4ade80; --red:#f87171; }
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',system-ui,sans-serif; }
        body { background:linear-gradient(160deg,#0d1117,#0a0e14); color:var(--text); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px; }
        .wrap { width:100%; max-width:640px; }
        h1 { font-size:22px; margin-bottom:4px; }
        .sub { color:var(--muted); font-size:13px; margin-bottom:22px; }
        .total { display:inline-flex; align-items:center; gap:8px; background:rgba(74,222,128,.12); border:1px solid rgba(74,222,128,.3);
                 color:var(--green); font-size:13px; font-weight:700; padding:6px 14px; border-radius:999px; margin-bottom:18px; }
        .card { background:var(--card); border:1px solid var(--border); border-radius:14px; padding:16px 18px; margin-bottom:12px;
                display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
        .card .uptime { flex-basis:100%; }
        .dot { width:11px; height:11px; border-radius:50%; flex-shrink:0; }
        .on  { background:var(--green); box-shadow:0 0 10px var(--green); }
        .off { background:var(--red); }
        .name { font-weight:700; flex:1; }
        .meta { color:var(--muted); font-size:13px; text-align:right; }
        .updated { color:var(--muted); font-size:12px; text-align:center; margin-top:16px; }
        .empty { text-align:center; color:var(--muted); padding:32px 0; }
        .uptime { margin-top: 10px; }
        .uptime-bars { display: flex; gap: 2px; align-items: flex-end; }
        .uptime-bars i { flex: 1; height: 18px; border-radius: 2px; background: #2c2f45; display:block; }
        .uptime-bars i.u100 { background: #22c55e; }
        .uptime-bars i.u90 { background: #84cc16; }
        .uptime-bars i.u50 { background: #f59e0b; }
        .uptime-bars i.u0 { background: #ef4444; }
        .uptime-label { font-size: 11px; color: #9a9fb8; margin-top: 4px; display: flex; justify-content: space-between; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>🌍 {{ __('messages.status.title') }}</h1>
    <p class="sub">{{ __('messages.status.subtitle') }}</p>

    <div class="total" id="total" hidden>👥 <span id="total-count">0</span> {{ __('messages.status.players_online') }}</div>

    <div id="servers">
        @forelse($statuses as $s)
            <div class="card">
                <span class="dot {{ !empty($s['online']) ? 'on' : 'off' }}"></span>
                <span class="name">{{ $s['name'] ?? $s['id'] ?? '—' }}</span>
                <span class="meta">
                    @if(!empty($s['online']))
                        {{ $s['players'] ?? '?' }}/{{ $s['max_players'] ?? '?' }} · {{ isset($s['latency']) && $s['latency'] !== null ? $s['latency'] . ' ms' : '—' }}
                    @else
                        {{ __('messages.status.offline') }}
                    @endif
                </span>
                @php
                    $h = $history[($s['ip'] ?? '') . ':' . ($s['port'] ?? '')] ?? null;
                @endphp
                @if($h && count($h['days']))
                    <div class="uptime">
                        <div class="uptime-bars">
                            @for($d = 29; $d >= 0; $d--)
                                @php
                                    $day = now()->subDays($d)->toDateString();
                                    $up = $h['days'][$day] ?? null;
                                    $cls = $up === null ? '' : ($up >= 99.5 ? 'u100' : ($up >= 90 ? 'u90' : ($up >= 50 ? 'u50' : 'u0')));
                                @endphp
                                <i class="{{ $cls }}" title="{{ $day }}{{ $up !== null ? ' — ' . $up . '%' : '' }}"></i>
                            @endfor
                        </div>
                        <div class="uptime-label">
                            <span>{{ __('messages.status.uptime_30d') }}</span>
                            <span>
                                @if($h['latency'] !== null){{ __('messages.status.avg_latency') }} : {{ $h['latency'] }} ms@endif
                            </span>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <div class="empty">{{ __('messages.status.no_data') }}</div>
        @endforelse
    </div>

    <div class="updated" id="updated"></div>
</div>

<script>
(function () {
    var last = Date.now();

    function render(list) {
        var box = document.getElementById('servers');
        if (!Array.isArray(list) || !list.length) return;
        var total = 0, html = '';
        list.forEach(function (s) {
            var on = !!s.online;
            if (on && typeof s.players === 'number') total += s.players;
            html += '<div class="card"><span class="dot ' + (on ? 'on' : 'off') + '"></span>'
                 + '<span class="name">' + String(s.name || s.id || '—').replace(/[<>&]/g, '') + '</span>'
                 + '<span class="meta">' + (on
                        ? ((s.players ?? '?') + '/' + (s.max_players ?? '?') + ' · ' + (s.latency != null ? s.latency + ' ms' : '—'))
                        : '{{ __('messages.status.offline') }}')
                 + '</span></div>';
        });
        box.innerHTML = html;
        var totalEl = document.getElementById('total');
        totalEl.hidden = false;
        document.getElementById('total-count').textContent = total;
    }

    function refresh() {
        fetch('{{ url('utils/servers-status') }}', { headers: { 'Accept': 'application/json' } })
            .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
            .then(function (d) { last = Date.now(); render(d); })
            .catch(function () {});
    }

    function tickUpdated() {
        var s = Math.max(0, Math.round((Date.now() - last) / 1000));
        document.getElementById('updated').textContent = '{{ __('messages.status.updated_ago') }}'.replace(':s', s);
    }

    refresh();
    setInterval(refresh, 30000);
    setInterval(tickUpdated, 1000);
    tickUpdated();
})();
</script>
</body>
</html>
