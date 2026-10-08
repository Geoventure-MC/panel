@extends('dashboard.layout')
@section('title', __('messages.dashboard.title'))
@section('description', __('messages.dashboard.description'))
@section('content')
@php
    $PD = \App\Services\PublicDashboard::class;
    $n = fn ($v) => number_format((int) $v, 0, ',', ' ');
    $empty = fn ($key) => '<div class="empty"><strong>' . e(__('messages.dashboard.empty_title')) . '</strong>' . e(__('messages.dashboard.' . $key)) . '</div>';
    $tabs = \App\Http\Controllers\PlayerDashboardController::TABS;
@endphp

<div class="pills" id="servers" aria-live="polite">
    @forelse($servers as $s)
        @php $on = ! empty($s['online']); @endphp
        <span class="pill {{ $on ? 'on' : 'off' }}" data-id="{{ $s['id'] ?? '' }}"><i class="dot"></i>{{ $s['name'] ?? $s['id'] ?? '—' }}
            <small>@if($on){{ $s['players'] ?? '?' }}/{{ $s['max_players'] ?? '?' }}@else{{ __('messages.dashboard.server_offline') }}@endif</small>
            @if(isset($s['uptime24h']))<small class="up" title="{{ __('messages.dashboard.uptime_24h') }}">{{ rtrim(rtrim(number_format((float) $s['uptime24h'], 1, ',', ''), '0'), ',') }} %</small>@endif</span>
    @empty
        <span class="sub">{{ __('messages.dashboard.no_servers') }}</span>
    @endforelse
    <span class="total" id="total" @if(! count($servers)) hidden @endif><span id="total-count">{{ $online }}</span> {{ __('messages.dashboard.servers_online') }}</span>
    <a class="sub" href="{{ route('status') }}" style="margin:0 0 0 auto;align-self:center">{{ __('messages.dashboard.status_page') }}</a>
</div>

<nav class="tabs" role="tablist">
    @foreach($tabs as $t)
        <button role="tab" id="btn-{{ $t }}" data-tab="{{ $t }}" aria-controls="tab-{{ $t }}" aria-selected="{{ $t === $tab ? 'true' : 'false' }}">{{ __('messages.dashboard.tabs.' . $t) }}</button>
    @endforeach
</nav>

{{-- Classement des joueurs --}}
<section class="tab {{ $tab === 'classement' ? 'active' : '' }}" id="tab-classement" role="tabpanel" aria-labelledby="btn-classement">
    <h2 class="no-js-h">{{ __('messages.dashboard.tabs.classement') }}</h2>
    @if(count($leaderboard))
        <div class="card table-wrap"><table>
            <thead><tr><th>{{ __('messages.dashboard.rank') }}</th><th>{{ __('messages.dashboard.player') }}</th>
                <th class="num">{{ __('messages.dashboard.coins') }}</th><th class="num hide-sm">{{ __('messages.dashboard.playtime') }}</th></tr></thead>
            <tbody>
            @foreach($leaderboard as $row)
                @php $rk = (int) ($row['rank'] ?? $loop->iteration); $nm = (string) ($row['name'] ?? '?'); @endphp
                <tr><td class="rank r{{ $rk }}">{{ $rk }}</td>
                    <td>@if($PD::validName($nm))<a href="{{ route('players.show', $nm) }}">{{ $nm }}</a>@else{{ $nm }}@endif</td>
                    <td class="num">{{ isset($row['coins']) ? $n($row['coins']) : '—' }}</td>
                    <td class="num hide-sm">{{ isset($row['playtime']) ? $n($row['playtime']) . ' ' . __('messages.dashboard.hours') : '—' }}</td></tr>
            @endforeach
            </tbody></table></div>
    @else
        {!! $empty('empty_leaderboard') !!}
    @endif
</section>

{{-- Pays / factions --}}
<section class="tab {{ $tab === 'pays' ? 'active' : '' }}" id="tab-pays" role="tabpanel" aria-labelledby="btn-pays">
    <h2 class="no-js-h">{{ __('messages.dashboard.tabs.pays') }}</h2>
    @if(count($factions))
        <div class="card table-wrap"><table>
            <thead><tr><th>#</th><th>{{ __('messages.dashboard.country') }}</th><th class="num">{{ __('messages.dashboard.members') }}</th>
                <th class="num hide-sm">{{ __('messages.dashboard.power') }}</th><th class="num hide-sm">{{ __('messages.dashboard.bank') }}</th></tr></thead>
            <tbody>
            @foreach($factions as $f)
                @php $hx = $PD::hex($f['hex'] ?? null); @endphp
                <tr><td class="rank r{{ $loop->iteration }}">{{ $loop->iteration }}</td>
                    <td><i class="swatch" @if($hx) style="background:{{ $hx }}" @endif></i>{{ $f['name'] ?? '?' }}@if(!empty($f['tag'])) <small style="color:var(--d-muted)">[{{ $f['tag'] }}]</small>@endif</td>
                    <td class="num">{{ isset($f['members']) ? $n($f['members']) : '—' }}@if(isset($f['online']) && $f['online'] !== null) <small style="color:var(--d-green)">({{ $n($f['online']) }})</small>@endif</td>
                    <td class="num hide-sm">{{ isset($f['power']) ? $n($f['power']) : '—' }}</td>
                    <td class="num hide-sm">{{ isset($f['bank']) ? $n($f['bank']) : '—' }}</td></tr>
            @endforeach
            </tbody></table></div>
    @else
        {!! $empty('empty_factions') !!}
    @endif
</section>

{{-- Saison + hall of fame --}}
<section class="tab {{ $tab === 'saison' ? 'active' : '' }}" id="tab-saison" role="tabpanel" aria-labelledby="btn-saison">
    <h2 class="no-js-h">{{ __('messages.dashboard.tabs.saison') }}</h2>
    @php $cur = $seasons['current']; @endphp
    @if($cur)
        <div class="card">
            <h3>{{ __('messages.dashboard.season_current') }} : {{ $cur['name'] ?? '' }}</h3>
            <p class="sub">{{ __('messages.dashboard.season_ends', ['date' => $PD::fmtDate($cur['endsAt'] ?? null)]) }}</p>
            @if(count($cur['standings'] ?? []))
                <div class="table-wrap"><table>
                    <thead><tr><th>#</th><th>{{ __('messages.dashboard.country') }}</th><th class="num">{{ __('messages.dashboard.points') }}</th></tr></thead>
                    <tbody>@foreach($cur['standings'] as $st)
                        <tr><td class="rank r{{ $loop->iteration }}">{{ $loop->iteration }}</td><td>{{ $st['name'] ?? '?' }}</td><td class="num">{{ $n($st['points'] ?? 0) }}</td></tr>
                    @endforeach</tbody></table></div>
            @else
                {!! $empty('empty_standings') !!}
            @endif
        </div>
    @else
        {!! $empty('empty_season') !!}
    @endif
    <h3 class="cat">{{ __('messages.dashboard.hall_of_fame') }}</h3>
    @if(count($seasons['past']))
        <div class="grid">
        @foreach($seasons['past'] as $ps)
            <div class="tile">
                <div class="v">{{ $ps['name'] ?? '' }}</div>
                <div class="k">{{ __('messages.dashboard.ended_on', ['date' => $PD::fmtDate($ps['endedAt'] ?? null)]) }}</div>
                @if(!empty($ps['winner']['name']) || !empty($ps['winner']['faction']))
                    <p style="margin-top:8px">{{ __('messages.dashboard.winner') }} :
                        <strong style="color:var(--d-gold)">{{ $ps['winner']['faction'] ?? $ps['winner']['name'] }}</strong>
                        @if(isset($ps['winner']['score'])) <small style="color:var(--d-muted)">({{ $n($ps['winner']['score']) }})</small>@endif</p>
                @endif
                @if(!empty($ps['reward']))<p class="k" style="margin-top:4px">{{ __('messages.dashboard.reward') }} : {{ $ps['reward'] }}</p>@endif
            </div>
        @endforeach
        </div>
    @else
        {!! $empty('empty_past') !!}
    @endif
</section>

{{-- Wonder --}}
<section class="tab {{ $tab === 'wonder' ? 'active' : '' }}" id="tab-wonder" role="tabpanel" aria-labelledby="btn-wonder">
    <h2 class="no-js-h">{{ __('messages.dashboard.tabs.wonder') }}</h2>
    @php $w = $wonder['current']; @endphp
    @if($w)
        @php $ph = in_array($w['phase'] ?? '', ['upcoming','open','closed','published'], true) ? $w['phase'] : 'upcoming'; @endphp
        <div class="card">
            <h3>{{ $w['name'] ?? '' }} <span class="badge {{ $ph }}">{{ __('messages.dashboard.phase.' . $ph) }}</span></h3>
            <div class="grid">
                <div class="tile"><div class="k">{{ __('messages.dashboard.wonder_theme') }}</div><div class="v">{{ $w['theme'] ?? '—' }}</div></div>
                <div class="tile"><div class="k">{{ __('messages.dashboard.wonder_world') }}</div><div class="v">{{ $w['world'] ?? '—' }}</div></div>
                <div class="tile"><div class="k">{{ __('messages.dashboard.wonder_opens') }}</div><div class="v" style="font-size:14px">{{ $PD::fmtDate($w['opensAt'] ?? null) }}</div></div>
                <div class="tile"><div class="k">{{ __('messages.dashboard.wonder_closes') }}</div><div class="v" style="font-size:14px">{{ $PD::fmtDate($w['closesAt'] ?? null) }}</div></div>
                @if(isset($w['teamSize']))<div class="tile"><div class="k">{{ __('messages.dashboard.wonder_team_size') }}</div><div class="v">{{ (int) $w['teamSize'] }}</div></div>@endif
                @if(!empty($w['weights']))<div class="tile"><div class="k">{{ __('messages.dashboard.wonder_weights') }}</div>
                    <div style="font-size:13px">{{ __('messages.dashboard.wonder_technical') }} {{ (float) ($w['weights']['technical'] ?? 0) }} % · {{ __('messages.dashboard.wonder_aesthetic') }} {{ (float) ($w['weights']['aesthetic'] ?? 0) }} % · {{ __('messages.dashboard.wonder_theme_weight') }} {{ (float) ($w['weights']['theme'] ?? 0) }} %</div></div>@endif
            </div>
            @if(!empty($w['rewards']))<p class="sub" style="margin-top:12px"><strong>{{ __('messages.dashboard.wonder_rewards') }} :</strong> {{ is_array($w['rewards']) ? implode(', ', array_map('strval', $w['rewards'])) : $w['rewards'] }}</p>@endif
        </div>

        @if(count($w['ranking'] ?? []))
            <h3 class="cat">{{ __('messages.dashboard.wonder_results') }}</h3>
            <div class="card table-wrap"><table>
                <thead><tr><th>#</th><th>{{ __('messages.dashboard.wonder_team') }}</th><th class="num">{{ __('messages.dashboard.wonder_total') }}</th></tr></thead>
                <tbody>@foreach($w['ranking'] as $r)
                    <tr><td class="rank r{{ (int) ($r['rank'] ?? $loop->iteration) }}">{{ (int) ($r['rank'] ?? $loop->iteration) }}</td>
                        <td>{{ $r['team'] ?? '?' }}</td><td class="num">{{ isset($r['total']) ? round((float) $r['total'], 1) : '—' }}</td></tr>
                @endforeach</tbody></table></div>
        @endif

        <h3 class="cat">{{ __('messages.dashboard.wonder_gallery') }}</h3>
        @if(count($w['gallery'] ?? []))
            <div class="gallery">
            @foreach($w['gallery'] as $t)
                @php $hx = $PD::hex($t['color'] ?? null); @endphp
                <div class="team" @if($hx) style="border-top-color:{{ $hx }}" @endif>
                    <h4>@if(!empty($t['rank']))#{{ (int) $t['rank'] }} @endif{{ $t['team'] ?? '?' }}</h4>
                    <div class="m">{{ implode(', ', array_map('strval', array_slice((array) ($t['members'] ?? []), 0, 24))) }}</div>
                    @foreach(array_slice((array) ($t['images'] ?? []), 0, 4) as $img)
                        @if(is_string($img) && preg_match('#^https://#i', $img) && filter_var($img, FILTER_VALIDATE_URL))
                            <img src="{{ $img }}" alt="{{ $t['team'] ?? '' }}" loading="lazy" referrerpolicy="no-referrer">
                        @endif
                    @endforeach
                </div>
            @endforeach
            </div>
        @else
            <div class="empty">{{ __('messages.dashboard.wonder_no_gallery') }}</div>
        @endif
    @else
        {!! $empty('empty_wonder') !!}
    @endif

    @if(count($wonder['past']))
        <h3 class="cat">{{ __('messages.dashboard.wonder_past') }}</h3>
        @foreach($wonder['past'] as $pw)
            <div class="card">
                <h3>{{ $pw['name'] ?? '' }} <small style="color:var(--d-muted)">— {{ $pw['theme'] ?? '' }}</small></h3>
                @foreach(array_slice((array) ($pw['ranking'] ?? []), 0, 3) as $r)
                    <div>#{{ (int) ($r['rank'] ?? $loop->iteration) }} {{ $r['team'] ?? '?' }} <small style="color:var(--d-muted)">{{ isset($r['total']) ? round((float) $r['total'], 1) : '' }}</small></div>
                @endforeach
            </div>
        @endforeach
    @endif
</section>

{{-- Collecte --}}
<section class="tab {{ $tab === 'collecte' ? 'active' : '' }}" id="tab-collecte" role="tabpanel" aria-labelledby="btn-collecte">
    <h2 class="no-js-h">{{ __('messages.dashboard.tabs.collecte') }}</h2>
    @php
        $c = $collect; $goal = (int) ($c['goal'] ?? 0); $tot = (int) ($c['total'] ?? 0);
        $pct = $goal > 0 ? min(100, (int) round($tot * 100 / $goal)) : 0;
        $st = in_array($c['state'] ?? '', ['collecting','boss'], true) ? $c['state'] : 'idle';
    @endphp
    @if(! empty($c['active']) || $goal > 0)
        <div class="card">
            <h3>{{ $c['title'] ?? '' }} <span class="badge {{ $st === 'idle' ? '' : 'open' }}">{{ __('messages.dashboard.collect_state_' . $st) }}</span>
                <span class="badge">{{ __('messages.dashboard.collect_tier', ['n' => (int) ($c['tier'] ?? 0)]) }}</span></h3>
            <div class="bar" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"><i style="width:{{ $pct }}%"></i></div>
            <div class="grid">
                <div class="tile"><div class="k">{{ __('messages.dashboard.collect_total') }}</div><div class="v">{{ $n($tot) }}</div></div>
                <div class="tile"><div class="k">{{ __('messages.dashboard.collect_goal') }}</div><div class="v">{{ $n($goal) }} <small style="color:var(--d-muted)">({{ $pct }} %)</small></div></div>
                @if(!empty($c['bossAt']))<div class="tile"><div class="k">{{ __('messages.dashboard.collect_boss') }}</div><div class="v" style="font-size:14px">{{ $PD::fmtDate($c['bossAt']) }}@if(!empty($c['bossLabel'])) · {{ $c['bossLabel'] }}@endif</div></div>@endif
            </div>
        </div>
        @if(count($c['top'] ?? []))
            <h3 class="cat">{{ __('messages.dashboard.collect_top') }}</h3>
            <div class="card table-wrap"><table>
                <thead><tr><th>#</th><th>{{ __('messages.dashboard.player') }}</th><th class="hide-sm">{{ __('messages.dashboard.country') }}</th><th class="num">{{ __('messages.dashboard.points') }}</th></tr></thead>
                <tbody>@foreach($c['top'] as $r)
                    @php $nm = (string) ($r['name'] ?? '?'); @endphp
                    <tr><td class="rank r{{ (int) ($r['rank'] ?? $loop->iteration) }}">{{ (int) ($r['rank'] ?? $loop->iteration) }}</td>
                        <td>@if($PD::validName($nm))<a href="{{ route('players.show', $nm) }}">{{ $nm }}</a>@else{{ $nm }}@endif</td>
                        <td class="hide-sm">{{ $r['country'] ?? '' }}</td><td class="num">{{ $n($r['points'] ?? 0) }}</td></tr>
                @endforeach</tbody></table></div>
        @endif
        @if(count($c['countries'] ?? []))
            <h3 class="cat">{{ __('messages.dashboard.collect_countries') }}</h3>
            <div class="card table-wrap"><table><tbody>@foreach($c['countries'] as $r)
                <tr><td class="rank r{{ (int) ($r['rank'] ?? $loop->iteration) }}">{{ (int) ($r['rank'] ?? $loop->iteration) }}</td><td>{{ $r['name'] ?? '?' }}</td><td class="num">{{ $n($r['points'] ?? 0) }}</td></tr>
            @endforeach</tbody></table></div>
        @endif
    @else
        {!! $empty('empty_collect') !!}
    @endif
</section>

{{-- Succès --}}
<section class="tab {{ $tab === 'succes' ? 'active' : '' }}" id="tab-succes" role="tabpanel" aria-labelledby="btn-succes">
    <h2 class="no-js-h">{{ __('messages.dashboard.tabs.succes') }}</h2>
    @if(count($achievements))
        @foreach(collect($achievements)->groupBy(fn ($a) => $a['category'] ?? '') as $cat => $list)
            @if($cat !== '')<h3 class="cat">{{ $cat }}</h3>@endif
            <div class="grid">
            @foreach($list as $a)
                @php $r = in_array($a['rarity'] ?? '', ['common','uncommon','rare','epic','legendary'], true) ? $a['rarity'] : 'common'; $sec = ! empty($a['secret']); @endphp
                <div class="ach rarity-{{ $r }} {{ $sec ? 'secret' : '' }}">
                    <h4>{{ $sec ? '???' : ($a['name'] ?? '') }}</h4>
                    <p>{{ $sec ? __('messages.dashboard.ach_secret') : ($a['description'] ?? '') }}</p>
                    <div class="meta"><span>{{ __('messages.dashboard.rarity.' . $r) }}</span>
                        <span>{{ __('messages.dashboard.ach_points', ['n' => (int) ($a['points'] ?? 0)]) }}</span>
                        @if((int) ($a['max_level'] ?? 1) > 1)<span>{{ __('messages.dashboard.ach_levels', ['n' => (int) $a['max_level']]) }}</span>@endif</div>
                </div>
            @endforeach
            </div>
        @endforeach
    @else
        {!! $empty('empty_achievements') !!}
    @endif
</section>

<script>
(function () {
    var tabs = ['classement', 'pays', 'saison', 'wonder', 'collecte', 'succes'];
    function show(t, push) {
        if (tabs.indexOf(t) < 0) t = 'classement';
        tabs.forEach(function (k) {
            var p = document.getElementById('tab-' + k), b = document.getElementById('btn-' + k);
            p.classList.toggle('active', k === t);
            b.setAttribute('aria-selected', k === t ? 'true' : 'false');
        });
        if (push) { try { history.replaceState(null, '', '#' + t); } catch (e) {} }
    }
    document.querySelectorAll('nav.tabs button').forEach(function (b) {
        b.addEventListener('click', function () { show(b.dataset.tab, true); });
    });
    var h = (location.hash || '').replace('#', '');
    if (tabs.indexOf(h) >= 0) show(h, false);

    var L = {up: @json(__('messages.dashboard.uptime_24h')), on: @json(__('messages.dashboard.server_online')), off: @json(__('messages.dashboard.server_offline'))};
    function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }
    function refresh() {
        fetch('/utils/servers-status', {headers: {Accept: 'application/json'}}).then(function (r) { return r.ok ? r.json() : null; }).then(function (list) {
            if (!Array.isArray(list) || !list.length) return;
            var box = document.getElementById('servers'), total = 0;
            box.querySelectorAll('.pill').forEach(function (n) { n.remove(); });
            var anchor = document.getElementById('total');
            list.forEach(function (s) {
                var on = !!s.online; if (on) total += parseInt(s.players || 0, 10) || 0;
                var p = el('span', 'pill ' + (on ? 'on' : 'off')); p.setAttribute('data-id', String(s.id == null ? '' : s.id)); p.appendChild(el('i', 'dot'));
                p.appendChild(document.createTextNode(String(s.name || s.id || '')));
                p.appendChild(el('small', null, on ? (s.players == null ? '?' : s.players) + '/' + (s.max_players == null ? '?' : s.max_players) : L.off));
                box.insertBefore(p, anchor);
            });
            setUptime();
            document.getElementById('total-count').textContent = total; anchor.hidden = false;
        }).catch(function () {});
    }
    var UP = {};
    function setUptime() {
        document.querySelectorAll('#servers .pill').forEach(function (p) {
            var id = p.getAttribute('data-id'); var v = id != null ? UP[id] : null;
            var u = p.querySelector('small.up');
            if (v == null) { if (u) u.remove(); return; }
            if (!u) { u = el('small', 'up'); u.title = L.up; p.appendChild(u); }
            u.textContent = (Math.round(v * 10) / 10).toString().replace('.', ',') + ' %';
        });
    }
    function refreshUptime() {
        fetch('/utils/uptime', {headers: {Accept: 'application/json'}}).then(function (r) { return r.ok ? r.json() : null; }).then(function (d) {
            if (!d || !Array.isArray(d.servers)) return;
            UP = {}; d.servers.forEach(function (s) { UP[s.id] = s.uptime24h; });
            setUptime();
        }).catch(function () {});
    }
    refresh(); setInterval(refresh, 30000); refreshUptime(); setInterval(refreshUptime, 120000);
})();
</script>
@endsection
