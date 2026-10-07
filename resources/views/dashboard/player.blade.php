@extends('dashboard.layout')
@section('title', $p['name'] . ' — ' . __('messages.dashboard.profile'))
@section('description', __('messages.dashboard.profile_desc', ['name' => $p['name']]))
@section('content')
@php $n = fn ($v) => number_format((int) $v, 0, ',', ' '); @endphp
<p class="sub"><a href="{{ route('players.index') }}">&larr; {{ __('messages.dashboard.back') }}</a></p>
<div class="card">
    <div class="profile-head">
        <h2>{{ $p['name'] }}</h2>
        @if($p['country'])
            @php $hx = \App\Services\PublicDashboard::hex($p['country']['hex'] ?? null); @endphp
            <span class="pill"><i class="swatch" @if($hx) style="background:{{ $hx }}" @endif></i>{{ $p['country']['name'] }}@if(!empty($p['country']['tag'])) <small>[{{ $p['country']['tag'] }}]</small>@endif</span>
        @endif
    </div>
    <div class="grid" style="margin-top:14px">
        @if($p['rank'])<div class="tile"><div class="k">{{ __('messages.dashboard.rank') }}</div><div class="v">#{{ $n($p['rank']) }}</div></div>@endif
        @if($p['coins'] !== null)<div class="tile"><div class="k">{{ __('messages.dashboard.coins') }}</div><div class="v">{{ $n($p['coins']) }}</div></div>@endif
        @if($p['playtime'] !== null)<div class="tile"><div class="k">{{ __('messages.dashboard.playtime') }}</div><div class="v">{{ $n($p['playtime']) }} {{ __('messages.dashboard.hours') }}</div></div>@endif
        <div class="tile"><div class="k">{{ __('messages.dashboard.profile_points') }}</div><div class="v">{{ $n($p['points']) }}</div></div>
        <div class="tile"><div class="k">{{ __('messages.dashboard.profile_unlocked') }}</div><div class="v">{{ count($p['achievements']) }}</div></div>
    </div>
</div>
<h3 class="cat">{{ __('messages.dashboard.profile_unlocked') }}</h3>
@if(count($p['achievements']))
    <div class="grid">
        @foreach($p['achievements'] as $a)
            @php $r = in_array($a['rarity'], ['common','uncommon','rare','epic','legendary'], true) ? $a['rarity'] : 'common'; @endphp
            <div class="ach rarity-{{ $r }} {{ $a['secret'] ? 'secret' : '' }}">
                <h4>{{ $a['name'] }}</h4>
                @if($a['description'] !== '')<p>{{ $a['description'] }}</p>@endif
                <div class="meta"><span>{{ __('messages.dashboard.rarity.' . $r) }}</span><span>{{ __('messages.dashboard.ach_points', ['n' => $a['points']]) }}</span>@if($a['at'])<span>{{ $a['at'] }}</span>@endif</div>
            </div>
        @endforeach
    </div>
@else
    <div class="empty">{{ __('messages.dashboard.profile_none') }}</div>
@endif
@endsection
