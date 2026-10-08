@extends('layouts.admin')

@section('title', __('mcp.title'))

@section('page-title', __('mcp.header'))

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@unless($secure)
    <div class="alert alert-warning" id="mcp-https-warning"><i class="bi bi-exclamation-triangle me-2"></i>{{ __('mcp.https_warning') }}</div>
@endunless

@if($newKey)
    <div class="alert alert-success" id="mcp-new-key">
        <h6 class="mb-2"><i class="bi bi-key me-1"></i>{{ __('mcp.new_key_title', ['name' => $newKeyName]) }}</h6>
        <p class="mb-2 fw-semibold">{{ __('mcp.new_key_warning') }}</p>
        <input type="text" class="form-control font-monospace" id="mcp-new-key-value" readonly value="{{ $newKey }}" onclick="this.select()">
    </div>
@endif

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <p class="text-muted">{{ __('mcp.intro') }}</p>
        <p class="mb-3"><strong>{{ __('mcp.endpoint') }} :</strong> <code>{{ $endpoint }}</code></p>
        <form action="{{ route('admin.mcp.settings') }}" method="POST" class="d-flex align-items-center gap-3">
            @csrf
            <div class="form-check form-switch mb-0">
                <input type="hidden" name="enabled" value="0">
                <input class="form-check-input" type="checkbox" role="switch" id="mcp-enabled" name="enabled" value="1" {{ $enabled ? 'checked' : '' }}>
                <label class="form-check-label" for="mcp-enabled">{{ __('mcp.enable_label') }}</label>
            </div>
            <span class="badge {{ $enabled ? 'bg-success' : 'bg-secondary' }}">{{ $enabled ? __('mcp.enabled') : __('mcp.disabled') }}</span>
            <button type="submit" class="btn btn-primary btn-sm ms-auto">{{ __('mcp.save') }}</button>
        </form>
    </div>
</div>

<div class="row">
    <div class="col-lg-5 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header fw-semibold"><i class="bi bi-plus-circle me-1"></i> {{ __('mcp.create') }}</div>
            <div class="card-body">
                <form action="{{ route('admin.mcp.keys.store') }}" method="POST" id="mcp-create-form">
                    @csrf
                    @if($errors->any())
                        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
                    @endif
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('mcp.name') }}</label>
                        <input type="text" name="name" class="form-control" maxlength="100" required value="{{ old('name') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('mcp.scope') }}</label>
                        <select name="scope" class="form-select">
                            <option value="read">{{ __('mcp.scope_read') }}</option>
                            <option value="write">{{ __('mcp.scope_write') }}</option>
                            <option value="admin">{{ __('mcp.scope_admin') }}</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('mcp.tools') }}</label>
                        <select name="allowed_tools[]" class="form-select" multiple size="6">
                            @foreach($tools as $name => $tool)
                                <option value="{{ $name }}">{{ $name }} ({{ $tool->scope }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('mcp.ips') }}</label>
                        <input type="text" name="allowed_ips" class="form-control" maxlength="1000" value="{{ old('allowed_ips') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('mcp.expires') }}</label>
                        <input type="datetime-local" name="expires_at" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-key me-1"></i> {{ __('mcp.create') }}</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header fw-semibold"><i class="bi bi-robot me-1"></i> {{ __('mcp.keys') }}</div>
            <div class="card-body p-0">
                @if($keys->isEmpty())
                    <p class="text-muted p-3 mb-0">{{ __('mcp.no_keys') }}</p>
                @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="mcp-keys">
                        <thead class="table-light"><tr>
                            <th>{{ __('mcp.name') }}</th><th>{{ __('mcp.prefix') }}</th><th>{{ __('mcp.scope') }}</th>
                            <th>{{ __('mcp.status') }}</th><th>{{ __('mcp.last_used') }}</th><th></th>
                        </tr></thead>
                        <tbody>
                        @foreach($keys as $k)
                            <tr data-key-name="{{ $k->name }}">
                                <td>{{ $k->name }}</td>
                                <td><code>{{ $k->key_prefix }}…</code></td>
                                <td><span class="badge bg-info text-dark">{{ $k->scope }}</span></td>
                                <td>
                                    @php($st = $k->status())
                                    <span class="badge {{ $st === 'active' ? 'bg-success' : 'bg-secondary' }}">{{ __('mcp.' . $st) }}</span>
                                </td>
                                <td class="text-muted small">{{ $k->last_used_at ? $k->last_used_at->format('d/m/Y H:i') : __('mcp.never') }}</td>
                                <td>
                                    @if($st === 'active')
                                    <form action="{{ route('admin.mcp.keys.revoke', $k) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('mcp.confirm_revoke') }}')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('mcp.revoke') }}</button>
                                    </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header fw-semibold d-flex align-items-center gap-2">
        <i class="bi bi-list-ul"></i> {{ __('mcp.calls') }}
        <form method="GET" class="ms-auto d-flex gap-2">
            <select name="key" class="form-select form-select-sm">
                <option value="">{{ __('mcp.all_keys') }}</option>
                @foreach($keys as $k)<option value="{{ $k->id }}" {{ (string) $keyFilter === (string) $k->id ? 'selected' : '' }}>{{ $k->name }}</option>@endforeach
            </select>
            <button class="btn btn-sm btn-outline-secondary">{{ __('mcp.filter') }}</button>
        </form>
    </div>
    <div class="card-body p-0">
        @if($calls->isEmpty())
            <p class="text-muted p-3 mb-0">{{ __('mcp.no_calls') }}</p>
        @else
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" id="mcp-calls">
                <thead class="table-light"><tr>
                    <th>{{ __('mcp.date') }}</th><th>{{ __('mcp.key') }}</th><th>{{ __('mcp.tool') }}</th><th>{{ __('mcp.result') }}</th><th>{{ __('mcp.duration') }}</th>
                </tr></thead>
                <tbody>
                @foreach($calls as $c)
                    <tr>
                        <td class="text-muted small text-nowrap">{{ $c->created_at?->format('d/m/Y H:i:s') }}</td>
                        <td>{{ $c->key_name }}</td>
                        <td><code>{{ $c->tool }}</code></td>
                        <td>
                            <span class="badge {{ $c->status === 'ok' ? 'bg-success' : ($c->status === 'denied' ? 'bg-warning text-dark' : 'bg-danger') }}">{{ $c->status }}</span>
                            @if($c->error)<span class="small text-muted">{{ \Illuminate\Support\Str::limit($c->error, 80) }}</span>@endif
                        </td>
                        <td class="text-muted small">{{ $c->duration_ms }} ms</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-3">{{ $calls->links() }}</div>
        @endif
    </div>
</div>
@endsection
