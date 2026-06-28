@extends('layouts.admin')

@section('title', __('messages.live.title'))

@section('page-title', __('messages.live.header'))

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">
            <i class="bi bi-arrow-repeat me-1"></i>{{ __('messages.live.auto_refresh') }}
            <span class="badge bg-secondary ms-1" id="live-updated">—</span>
        </p>
    </div>

    {{-- Total joueurs connectés --}}
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">{{ __('messages.live.total_players') }}</h5>
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="mb-0 fw-bold" id="live-total-players">{{ $totalPlayers }}</h2>
                        <i class="bi bi-people-fill fs-1 text-primary opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Statut serveurs --}}
    <h5 class="mb-3"><i class="bi bi-hdd-network me-1"></i>{{ __('messages.live.servers') }}</h5>
    <div class="row mb-4" id="live-servers">
        @forelse($serverStatuses as $server)
        <div class="col-md-4 mb-3">
            <div class="card h-100 border-{{ $server['online'] ? 'success' : 'danger' }}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="card-title mb-1">{{ $server['name'] }}</h6>
                            <span class="badge bg-{{ $server['online'] ? 'success' : 'danger' }}">
                                {{ $server['online'] ? __('messages.live.online') : __('messages.live.offline') }}
                            </span>
                        </div>
                        <i class="bi bi-{{ $server['online'] ? 'wifi' : 'wifi-off' }} fs-3 text-{{ $server['online'] ? 'success' : 'danger' }} opacity-75"></i>
                    </div>
                    @if($server['online'])
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="bi bi-people-fill me-1"></i>{{ $server['players'] ?? 0 }} / {{ $server['max_players'] ?? '?' }}
                            @if($server['latency'])
                                <span class="ms-2"><i class="bi bi-speedometer2 me-1"></i>{{ $server['latency'] }}ms</span>
                            @endif
                        </small>
                    </div>
                    @endif
                    <div class="mt-1"><small class="text-muted">{{ $server['ip'] }}:{{ $server['port'] }}</small></div>
                </div>
            </div>
        </div>
        @empty
        <div class="col-12"><p class="text-muted">{{ __('messages.live.no_servers') }}</p></div>
        @endforelse
    </div>

    {{-- Derniers succès débloqués --}}
    <h5 class="mb-3"><i class="bi bi-trophy me-1"></i>{{ __('messages.live.recent_unlocks') }}</h5>
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('messages.live.player') }}</th>
                            <th>{{ __('messages.live.code') }}</th>
                            <th>{{ __('messages.live.when') }}</th>
                        </tr>
                    </thead>
                    <tbody id="live-unlocks">
                        @forelse($recentUnlocks as $u)
                        <tr>
                            <td>{{ $u->player }}</td>
                            <td><code>{{ $u->code }}</code></td>
                            <td class="text-muted small">{{ optional($u->unlocked_at ?? $u->created_at)->diffForHumans() }}</td>
                        </tr>
                        @empty
                        <tr id="live-unlocks-empty"><td colspan="3" class="text-muted p-3">{{ __('messages.live.no_unlocks') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    const FEED_URL = '{{ route('admin.dashboard.feed') }}';
    const T = {
        online: @json(__('messages.live.online')),
        offline: @json(__('messages.live.offline')),
        no_servers: @json(__('messages.live.no_servers')),
        no_unlocks: @json(__('messages.live.no_unlocks')),
    };

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    function renderServers(servers) {
        const wrap = document.getElementById('live-servers');
        if (!wrap) return;
        if (!servers || !servers.length) {
            wrap.innerHTML = '<div class="col-12"><p class="text-muted">' + esc(T.no_servers) + '</p></div>';
            return;
        }
        wrap.innerHTML = servers.map(function (s) {
            const ok = !!s.online;
            const players = (s.players == null ? 0 : s.players) + ' / ' + (s.max_players == null ? '?' : s.max_players);
            const latency = s.latency ? '<span class="ms-2"><i class="bi bi-speedometer2 me-1"></i>' + esc(s.latency) + 'ms</span>' : '';
            const info = ok ? '<div class="mt-2"><small class="text-muted"><i class="bi bi-people-fill me-1"></i>' + esc(players) + latency + '</small></div>' : '';
            return '<div class="col-md-4 mb-3"><div class="card h-100 border-' + (ok ? 'success' : 'danger') + '">' +
                '<div class="card-body"><div class="d-flex justify-content-between align-items-start"><div>' +
                '<h6 class="card-title mb-1">' + esc(s.name) + '</h6>' +
                '<span class="badge bg-' + (ok ? 'success' : 'danger') + '">' + esc(ok ? T.online : T.offline) + '</span></div>' +
                '<i class="bi bi-' + (ok ? 'wifi' : 'wifi-off') + ' fs-3 text-' + (ok ? 'success' : 'danger') + ' opacity-75"></i></div>' +
                info +
                '<div class="mt-1"><small class="text-muted">' + esc(s.ip) + ':' + esc(s.port) + '</small></div>' +
                '</div></div></div>';
        }).join('');
    }

    function renderUnlocks(unlocks) {
        const tbody = document.getElementById('live-unlocks');
        if (!tbody) return;
        if (!unlocks || !unlocks.length) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-muted p-3">' + esc(T.no_unlocks) + '</td></tr>';
            return;
        }
        tbody.innerHTML = unlocks.map(function (u) {
            return '<tr><td>' + esc(u.player) + '</td><td><code>' + esc(u.code) + '</code></td>' +
                '<td class="text-muted small">' + esc(u.ago) + '</td></tr>';
        }).join('');
    }

    async function poll() {
        try {
            const res = await fetch(FEED_URL, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            renderServers(data.servers);
            renderUnlocks(data.unlocks);
            const tp = document.getElementById('live-total-players');
            if (tp) tp.textContent = (data.totalPlayers == null ? 0 : data.totalPlayers);
            const upd = document.getElementById('live-updated');
            if (upd) upd.textContent = new Date().toLocaleTimeString();
        } catch (e) { /* non-fatal */ }
    }

    poll();
    setInterval(poll, 20000);
})();
</script>
@endsection
