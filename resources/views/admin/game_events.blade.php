@extends('layouts.admin')

@section('title', __('messages.game_events.title'))

@section('page-title', __('messages.game_events.header'))

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif
<p class="text-muted">{{ __('messages.game_events.hint') }}</p>

@foreach($events as $ev)
@php $s = $ev->settings ?? []; $tz = 'Europe/Paris'; @endphp
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header fw-semibold d-flex justify-content-between">
        <span>🎃 {{ $ev->name }} <code>{{ $ev->slug }}</code></span>
        <span class="badge bg-secondary">{{ __('messages.game_events.p_'.$ev->phase()) }}</span>
    </div>
    <div class="card-body">
        <form action="{{ route('admin.game-events.update', $ev) }}" method="POST">
            @csrf @method('PUT')
            <div class="row g-3">
                <div class="col-md-3 form-check form-switch ms-2">
                    <input class="form-check-input" type="checkbox" name="enabled" value="1" id="en{{ $ev->id }}" @checked($ev->enabled)>
                    <label class="form-check-label" for="en{{ $ev->id }}">{{ __('messages.game_events.enabled') }}</label>
                </div>
                <div class="col-md-4 form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="announce" value="1" id="an{{ $ev->id }}" @checked($ev->announce)>
                    <label class="form-check-label" for="an{{ $ev->id }}">{{ __('messages.game_events.announce') }}</label>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">{{ __('messages.game_events.opens') }}</label>
                    <input type="datetime-local" name="opens_at" class="form-control" value="{{ $ev->opens_at?->copy()->setTimezone($tz)->format('Y-m-d\TH:i') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">{{ __('messages.game_events.ends') }}</label>
                    <input type="datetime-local" name="ends_at" class="form-control" value="{{ $ev->ends_at?->copy()->setTimezone($tz)->format('Y-m-d\TH:i') }}">
                </div>
                @foreach(\App\Models\GameEvent::SETTINGS[$ev->slug] ?? [] as $k => [$min, $max])
                <div class="col-md-3">
                    <label class="form-label">{{ __('messages.game_events.'.$k) }}</label>
                    <input type="number" name="settings[{{ $k }}]" class="form-control" min="{{ $min }}" max="{{ $max }}" value="{{ $s[$k] ?? '' }}">
                </div>
                @endforeach
                @if(isset(\App\Models\GameEvent::SETTINGS[$ev->slug]))
                <div class="col-12"><label class="form-label">{{ __('messages.game_events.rewards') }}</label>
                    <div class="row g-2">
                    @for($i = 0; $i < 4; $i++)
                        <div class="col-3"><input type="number" name="rewards[]" class="form-control" min="0" max="1000000" value="{{ ($s['rewards'] ?? [5000,3000,1500,500])[$i] }}"></div>
                    @endfor
                    </div>
                </div>
                @endif
            </div>
            <button class="btn btn-primary mt-3"><i class="bi bi-save me-1"></i> {{ __('messages.game_events.save') }}</button>
        </form>
    </div>
</div>
@endforeach
@endsection
