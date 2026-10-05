@extends('layouts.admin')

@section('title', __('messages.wonder.title'))

@section('page-title', __('messages.wonder.header'))

@section('content')
@foreach(['success' => 'success', 'error' => 'danger', 'warning' => 'warning'] as $key => $cls)
    @if(session($key))
        <div class="alert alert-{{ $cls }} alert-dismissible fade show" role="alert">
            {{ session($key) }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
@endforeach
@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif
@unless($connected)
    <div class="alert alert-warning">
        <i class="bi bi-plug me-1"></i> {{ __('messages.wonder.not_connected') }} <code>GEO_GAME_DB_*</code>
    </div>
@endunless

@php
    $fields = function ($e = null) {
        return [
            'name' => old('name', $e->name ?? ''),
            'theme' => old('theme', $e->theme ?? ''),
            'world' => old('world', $e->world ?? 'wonder'),
            'opens_at' => old('opens_at', optional($e->opens_at ?? null)->format('Y-m-d\TH:i')),
            'closes_at' => old('closes_at', optional($e->closes_at ?? null)->format('Y-m-d\TH:i')),
            'team_size' => old('team_size', $e->team_size ?? 18),
            'builders' => old('builders', $e->builders ?? 3),
            'wt' => old('weight_technical', $e->weight_technical ?? 40),
            'wa' => old('weight_aesthetic', $e->weight_aesthetic ?? 30),
            'wh' => old('weight_theme', $e->weight_theme ?? 30),
            'rewards' => old('rewards', $e->rewards ?? ''),
        ];
    };
    $phaseCls = ['upcoming' => 'secondary', 'open' => 'success', 'closed' => 'warning', 'published' => 'primary'];
@endphp

<div class="row">
    <div class="col-lg-3 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header fw-semibold"><i class="bi bi-buildings me-1"></i> {{ __('messages.wonder.editions') }}</div>
            <div class="list-group list-group-flush">
                @forelse($editions as $e)
                    <a class="list-group-item list-group-item-action {{ $current && $current->id === $e->id ? 'active' : '' }}"
                       href="{{ route('admin.wonder', ['edition' => $e->id]) }}">
                        <div class="fw-semibold">{{ $e->name }}</div>
                        <span class="badge bg-{{ $phaseCls[$e->phase()] }}">{{ __('messages.wonder.phase.'.$e->phase()) }}</span>
                    </a>
                @empty
                    <div class="list-group-item text-muted">{{ __('messages.wonder.none') }}</div>
                @endforelse
            </div>
        </div>
        <div class="card shadow-sm border-0 mt-3">
            <div class="card-header fw-semibold">{{ __('messages.wonder.new_edition') }}</div>
            <div class="card-body">
                @php $f = $fields(null); @endphp
                <form action="{{ route('admin.wonder.store') }}" method="POST">
                    @csrf
                    @include('admin.partials.wonder_edition_fields', ['f' => $f])
                    <button class="btn btn-primary btn-sm mt-2">{{ __('messages.wonder.create') }}</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-9">
    @if($current)
        @php $f = $fields($current); $phase = $current->phase(); $locked = $current->status === 'published'; @endphp
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span class="fw-semibold">{{ $current->name }}
                    <span class="badge bg-{{ $phaseCls[$phase] }} ms-1">{{ __('messages.wonder.phase.'.$phase) }}</span>
                    @if($current->published_at)
                        <span class="text-muted small ms-2">{{ __('messages.wonder.published_on') }} {{ $current->published_at->format('d/m/Y H:i') }}</span>
                    @endif
                </span>
                <span class="d-flex gap-2">
                    <form action="{{ route('admin.wonder.sync', $current) }}" method="POST">@csrf
                        <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-repeat"></i> {{ __('messages.wonder.sync') }}</button>
                    </form>
                    <form action="{{ route('admin.wonder.destroy', $current) }}" method="POST"
                          onsubmit="return confirm('{{ __('messages.wonder.confirm_delete') }}')">@csrf @method('DELETE')
                        <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> {{ __('messages.wonder.delete') }}</button>
                    </form>
                </span>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">{{ __('messages.wonder.public_api') }} : <code>/utils/wonder</code></p>
                <form action="{{ route('admin.wonder.update', $current) }}" method="POST">
                    @csrf @method('PUT')
                    @include('admin.partials.wonder_edition_fields', ['f' => $f])
                    <button class="btn btn-primary btn-sm mt-2">{{ __('messages.wonder.save') }}</button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header fw-semibold"><i class="bi bi-people me-1"></i> {{ __('messages.wonder.teams') }} ({{ $teams->count() }})</div>
            <div class="card-body">
                @forelse($teams as $t)
                    <form action="{{ route('admin.wonder.teams.update', $t) }}" method="POST" class="border rounded p-3 mb-3">
                        @csrf @method('PUT')
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="d-inline-block rounded" style="width:14px;height:14px;background:{{ $t->color }}"></span>
                            <strong>{{ $t->name }}</strong>
                            <input type="color" name="color" value="{{ $t->color }}" class="form-control form-control-color form-control-sm ms-2">
                            <button type="submit" form="del-team-{{ $t->id }}" class="btn btn-outline-danger btn-sm ms-auto"
                                    onclick="return confirm('{{ __('messages.wonder.confirm_delete') }}')"><i class="bi bi-trash"></i></button>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label small">{{ __('messages.wonder.builders') }} ({{ count((array) $t->builders) }}/{{ $current->builders }})</label>
                                <textarea name="builders" rows="3" class="form-control form-control-sm">{{ implode("\n", (array) $t->builders) }}</textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">{{ __('messages.wonder.reserves') }}</label>
                                <textarea name="reserves" rows="3" class="form-control form-control-sm">{{ implode("\n", (array) $t->reserves) }}</textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">{{ __('messages.wonder.images') }}</label>
                                <textarea name="images" rows="3" class="form-control form-control-sm">{{ implode("\n", (array) $t->images) }}</textarea>
                            </div>
                        </div>
                        <button class="btn btn-primary btn-sm mt-2">{{ __('messages.wonder.save') }}</button>
                    </form>
                    <form id="del-team-{{ $t->id }}" action="{{ route('admin.wonder.teams.destroy', $t) }}" method="POST">@csrf @method('DELETE')</form>
                @empty
                    <p class="text-muted">{{ __('messages.wonder.no_teams') }}</p>
                @endforelse

                <h6 class="mt-4">{{ __('messages.wonder.add_team') }}</h6>
                <form action="{{ route('admin.wonder.teams.store', $current) }}" method="POST" class="row g-2">
                    @csrf
                    <div class="col-md-5"><input name="name" class="form-control form-control-sm" maxlength="32" required placeholder="{{ __('messages.wonder.team_name') }}"></div>
                    <div class="col-md-2"><input type="color" name="color" value="#a78bfa" class="form-control form-control-color form-control-sm"></div>
                    <div class="col-md-5"><button class="btn btn-primary btn-sm">{{ __('messages.wonder.add_team') }}</button></div>
                    <div class="col-md-6"><textarea name="builders" rows="2" class="form-control form-control-sm" placeholder="{{ __('messages.wonder.builders_hint') }}"></textarea></div>
                    <div class="col-md-6"><textarea name="reserves" rows="2" class="form-control form-control-sm" placeholder="{{ __('messages.wonder.reserves') }}"></textarea></div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header fw-semibold"><i class="bi bi-clipboard-check me-1"></i> {{ __('messages.wonder.jury') }}</div>
            <div class="card-body">
                <p class="text-muted small">{{ __('messages.wonder.note_hint') }}</p>
                @foreach($teams as $t)
                    <div class="mb-3">
                        <strong>{{ $t->name }}</strong>
                        @if($t->scores->isEmpty())
                            <span class="text-muted small ms-2">{{ __('messages.wonder.no_scores') }}</span>
                        @else
                            <table class="table table-sm align-middle mb-1">
                                <thead><tr><th>{{ __('messages.wonder.juror') }}</th><th>{{ __('messages.wonder.w_technical') }}</th><th>{{ __('messages.wonder.w_aesthetic') }}</th><th>{{ __('messages.wonder.w_theme') }}</th><th></th></tr></thead>
                                <tbody>
                                @foreach($t->scores as $s)
                                    <tr>
                                        <td>{{ $s->juror }}</td><td>{{ $s->technical }}</td><td>{{ $s->aesthetic }}</td><td>{{ $s->theme }}</td>
                                        <td class="text-end">
                                            @unless($locked)
                                            <form action="{{ route('admin.wonder.scores.destroy', $s) }}" method="POST">@csrf @method('DELETE')
                                                <button class="btn btn-link btn-sm text-danger p-0"><i class="bi bi-x-lg"></i></button>
                                            </form>
                                            @endunless
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        @endif
                        @unless($locked)
                        <form action="{{ route('admin.wonder.scores.store', $t) }}" method="POST" class="row g-1">
                            @csrf
                            <div class="col-sm-3"><input name="juror" class="form-control form-control-sm" maxlength="32" required placeholder="{{ __('messages.wonder.juror') }}"></div>
                            @foreach(['technical', 'aesthetic', 'theme'] as $k)
                                <div class="col-sm-2"><input name="{{ $k }}" type="number" step="0.5" min="0" max="10" required class="form-control form-control-sm" placeholder="{{ __('messages.wonder.w_'.$k) }}"></div>
                            @endforeach
                            <div class="col-sm-1"><button class="btn btn-outline-primary btn-sm">{{ __('messages.wonder.add_score') }}</button></div>
                        </form>
                        @endunless
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-award me-1"></i> {{ __('messages.wonder.ranking') }}</span>
                @unless($locked)
                <form action="{{ route('admin.wonder.publish', $current) }}" method="POST"
                      onsubmit="return confirm('{{ __('messages.wonder.confirm_publish') }}')">@csrf
                    <button class="btn btn-success btn-sm"><i class="bi bi-send"></i> {{ __('messages.wonder.publish') }}</button>
                </form>
                @endunless
            </div>
            <div class="card-body p-0">
                @if(empty($ranking))
                    <p class="text-muted p-3 mb-0">{{ __('messages.wonder.no_scores') }}</p>
                @else
                    <table class="table mb-0 align-middle">
                        <thead class="table-light"><tr>
                            <th>{{ __('messages.wonder.rank') }}</th><th>{{ __('messages.wonder.team_name') }}</th>
                            <th>{{ __('messages.wonder.w_technical') }}</th><th>{{ __('messages.wonder.w_aesthetic') }}</th><th>{{ __('messages.wonder.w_theme') }}</th>
                            <th>{{ __('messages.wonder.total') }}</th>
                        </tr></thead>
                        <tbody>
                        @foreach($ranking as $r)
                            <tr>
                                <td>{{ $r['rank'] }}</td>
                                <td><span class="d-inline-block rounded me-1" style="width:10px;height:10px;background:{{ $r['color'] }}"></span>{{ $r['team'] }}</td>
                                <td>{{ $r['technical'] }}</td><td>{{ $r['aesthetic'] }}</td><td>{{ $r['theme'] }}</td>
                                <td class="fw-semibold">{{ $r['total'] }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    @else
        <p class="text-muted">{{ __('messages.wonder.none') }}</p>
    @endif
    </div>
</div>
@endsection
