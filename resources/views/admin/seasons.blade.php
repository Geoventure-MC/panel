@extends('layouts.admin')

@section('title', __('messages.seasons.title'))

@section('page-title', __('messages.seasons.header'))

@section('content')
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        @if($seasons->isEmpty())
            <p class="text-muted p-4 mb-0">{{ __('messages.seasons.none') }}</p>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('messages.seasons.name') }}</th>
                            <th>{{ __('messages.seasons.status') }}</th>
                            <th>{{ __('messages.seasons.starts_at') }}</th>
                            <th>{{ __('messages.seasons.ends_at') }}</th>
                            <th>{{ __('messages.seasons.winner') }}</th>
                            <th>{{ __('messages.seasons.score') }}</th>
                            <th>{{ __('messages.seasons.reward') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($seasons as $season)
                        <tr>
                            <td>{{ $season->name }} <span class="text-muted small">({{ $season->external_id }})</span></td>
                            <td>
                                @if($season->status === 'active')
                                    <span class="badge bg-success">{{ __('messages.seasons.status_active') }}</span>
                                @else
                                    <span class="badge bg-secondary">{{ __('messages.seasons.status_ended') }}</span>
                                @endif
                            </td>
                            <td class="text-muted small text-nowrap">{{ $season->starts_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="text-muted small text-nowrap">{{ $season->ends_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td>
                                @if($season->winner_name)
                                    {{ $season->winner_name }}
                                    @if($season->winner_faction)
                                        <span class="text-muted small">— {{ $season->winner_faction }}</span>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-muted small">{{ $season->winner_score !== null ? number_format($season->winner_score, 0, ',', ' ') : '—' }}</td>
                            <td class="text-muted small">{{ $season->reward ?? '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
