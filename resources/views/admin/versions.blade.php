@extends('layouts.admin')

@section('title', __('versions.title'))

@section('page-title', __('versions.header'))

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@php
    $badge = ['uptodate' => 'bg-success', 'behind' => 'bg-danger', 'unknown' => 'bg-secondary'];
@endphp

<p class="text-muted">{{ __('versions.intro') }}</p>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        @foreach(['uptodate', 'behind', 'unknown'] as $st)
            <span class="badge {{ $badge[$st] }} me-1" data-summary="{{ $st }}">{{ __('versions.status_labels.' . $st) }} : {{ $summary[$st] }}</span>
        @endforeach
    </div>
    <form method="POST" action="{{ route('admin.versions.refresh') }}">
        @csrf
        <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-repeat me-1"></i> {{ __('versions.refresh') }}</button>
    </form>
</div>

<div class="alert {{ $hasToken ? 'alert-light' : 'alert-warning' }} border small py-2">
    <i class="bi bi-key me-1"></i> {{ $hasToken ? __('versions.token.present') : __('versions.token.absent') }}
</div>

<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle" id="versions-table">
            <thead><tr>
                <th>{{ __('versions.component') }}</th>
                <th>{{ __('versions.running') }}</th>
                <th>{{ __('versions.latest') }}</th>
                <th>{{ __('versions.status') }}</th>
                <th>{{ __('versions.source') }}</th>
            </tr></thead>
            <tbody>
            @foreach($rows as $r)
                @php
                    if ($r['running'] === null) {
                        $sourceText = __('versions.sources.none_' . $r['source']);
                    } elseif ($r['source'] === 'reported') {
                        $when = null;
                        try { $when = $r['detail'] ? \Carbon\Carbon::parse($r['detail'])->format('d/m/Y H:i') : null; } catch (\Throwable $e) { $when = null; }
                        $sourceText = $when ? __('versions.sources.reported', ['date' => $when]) : __('versions.sources.reported_nodate');
                    } else {
                        $sourceText = __('versions.sources.' . $r['source'], ['users' => $r['detail'] ?? '']);
                    }
                @endphp
                <tr data-component="{{ $r['key'] }}" data-status="{{ $r['status'] }}">
                    <td class="fw-semibold">{{ $r['label'] }}<div class="small text-muted fw-normal">{{ $r['repo'] }}</div></td>
                    <td>{{ $r['running'] ?? '—' }}</td>
                    <td>
                        @if($r['latest'] !== null)
                            <a href="{{ $r['url'] }}" target="_blank" rel="noopener noreferrer">{{ $r['latest'] }} <i class="bi bi-box-arrow-up-right small"></i></a>
                            @if($r['date'])<div class="small text-muted">{{ $r['date'] }}</div>@endif
                            @if($r['stale'])<div class="small text-warning">{{ __('versions.stale') }}</div>@endif
                        @else
                            <a href="{{ $r['url'] }}" target="_blank" rel="noopener noreferrer" class="text-muted">—</a>
                        @endif
                    </td>
                    <td>
                        <span class="badge {{ $badge[$r['status']] }}">{{ __('versions.status_labels.' . $r['status']) }}</span>
                        @if($r['reason'])<div class="small text-muted">{{ __('versions.reasons.' . $r['reason']) }}</div>@endif
                    </td>
                    <td class="small text-muted">{{ $sourceText }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

<p class="small text-muted mt-3">{{ __('versions.generated_at', ['date' => $generatedAt->format('d/m/Y H:i')]) }}</p>
@endsection
