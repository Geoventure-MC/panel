@extends('layouts.admin')

@section('title', __('messages.game_commands.title'))

@section('page-title', __('messages.game_commands.header'))

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@unless($connected)
    <div class="alert alert-warning">
        <i class="bi bi-plug me-1"></i> {{ __('messages.game_commands.not_connected') }}
        <code>GEO_GAME_DB_*</code>
    </div>
@endunless

<div class="row">
    <div class="col-md-5 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header fw-semibold">
                <i class="bi bi-send me-1"></i> {{ __('messages.game_commands.send') }}
            </div>
            <div class="card-body">
                <form action="{{ route('admin.game-commands.store') }}" method="POST">
                    @csrf
                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->all() as $e)
                                    <li>{{ $e }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <div class="mb-3">
                        <label class="form-label">{{ __('messages.game_commands.type') }}</label>
                        <select name="type" class="form-select" required>
                            @foreach($types as $t)
                                <option value="{{ $t }}">{{ __('messages.game_commands.types.'.$t) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('messages.game_commands.target') }}</label>
                        <input type="text" name="target" class="form-control" maxlength="255" required
                               placeholder="{{ __('messages.game_commands.target_hint') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('messages.game_commands.amount') }}</label>
                        <input type="number" name="amount" class="form-control" min="0" value="0">
                        <div class="form-text">{{ __('messages.game_commands.amount_hint') }}</div>
                    </div>
                    <button type="submit" class="btn btn-primary" @unless($connected) disabled @endunless>
                        <i class="bi bi-send me-1"></i> {{ __('messages.game_commands.submit') }}
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-7 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header fw-semibold">
                <i class="bi bi-clock-history me-1"></i> {{ __('messages.game_commands.history') }}
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>{{ __('messages.game_commands.type') }}</th>
                                <th>{{ __('messages.game_commands.target') }}</th>
                                <th>{{ __('messages.game_commands.amount') }}</th>
                                <th>{{ __('messages.game_commands.status') }}</th>
                                <th>{{ __('messages.game_commands.result') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($commands as $c)
                                <tr>
                                    <td class="text-muted">{{ $c->id }}</td>
                                    <td><code>{{ $c->type }}</code></td>
                                    <td>{{ \Illuminate\Support\Str::limit($c->target, 40) }}</td>
                                    <td>{{ $c->amount }}</td>
                                    <td>
                                        @if($c->status === 'done')
                                            <span class="badge bg-success">done</span>
                                        @elseif($c->status === 'failed')
                                            <span class="badge bg-danger">failed</span>
                                        @else
                                            <span class="badge bg-secondary">pending</span>
                                        @endif
                                    </td>
                                    <td class="text-muted small">{{ \Illuminate\Support\Str::limit($c->result, 60) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-3">
                                    {{ __('messages.game_commands.empty') }}
                                </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
