@extends('layouts.admin')

@section('title', __('messages.diagnostics.title'))

@section('page-title', __('messages.diagnostics.title'))

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<p class="text-muted">{{ __('messages.diagnostics.help', ['days' => \App\Models\LauncherDiagnostic::KEEP_DAYS]) }}</p>

<form class="row g-2 mb-3" method="GET" action="{{ route('admin.diagnostics') }}">
    <div class="col-md-5">
        <input type="search" name="q" value="{{ $search }}" class="form-control" placeholder="{{ __('messages.diagnostics.search') }}">
    </div>
    <div class="col-auto">
        <select name="show" class="form-select" onchange="this.form.submit()">
            <option value="">{{ __('messages.diagnostics.pending', ['count' => $pending]) }}</option>
            <option value="all" @selected($showAll)>{{ __('messages.diagnostics.all') }}</option>
        </select>
    </div>
    <div class="col-auto"><button class="btn btn-primary"><i class="bi bi-search"></i></button></div>
</form>

<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('messages.diagnostics.date') }}</th>
                    <th>{{ __('messages.diagnostics.summary') }}</th>
                    <th>{{ __('messages.diagnostics.launcher') }}</th>
                    <th>{{ __('messages.diagnostics.system') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($reports as $r)
                <tr class="{{ $r->handled ? 'text-muted' : '' }}">
                    <td class="text-nowrap">{{ $r->created_at->format('d/m H:i') }}</td>
                    <td><a href="{{ route('admin.diagnostics.show', $r) }}">{{ $r->summary }}</a>@if($r->instance) <span class="badge bg-secondary">{{ $r->instance }}</span>@endif</td>
                    <td class="text-nowrap">{{ $r->launcher_version }}</td>
                    <td class="text-nowrap">{{ $r->os }}</td>
                    <td class="text-end text-nowrap">
                        <form action="{{ route('admin.diagnostics.toggle', $r) }}" method="POST" class="d-inline">
                            @csrf @method('PATCH')
                            <button class="btn btn-sm {{ $r->handled ? 'btn-outline-secondary' : 'btn-outline-success' }}" title="{{ $r->handled ? __('messages.diagnostics.reopen') : __('messages.diagnostics.handled') }}">
                                <i class="bi {{ $r->handled ? 'bi-arrow-counterclockwise' : 'bi-check2' }}"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">{{ __('messages.diagnostics.empty') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $reports->links() }}</div>
@endsection
