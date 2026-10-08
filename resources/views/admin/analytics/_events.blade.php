@php $G = \App\Services\GameAnalytics::class; @endphp
<div class="table-responsive">
    <table class="table table-sm an-table align-middle">
        <thead><tr><th>{{ __('analytics.date') }}</th><th>{{ __('analytics.type') }}</th><th>{{ __('analytics.country') }}</th><th>{{ __('analytics.detail') }}</th></tr></thead>
        <tbody>
        @forelse ($events as $e)
            <tr data-event="{{ $e['type'] }}">
                <td class="text-nowrap">{{ $G::fmtDate($e['ts']) }}</td>
                <td><span class="an-chip">{{ $G::typeLabel($e['type']) }}</span></td>
                <td>{{ $G::eventRef($e['type'], $e['ref']) }}</td>
                <td class="small text-muted text-break">{{ $G::summarize($e['type'], $e['data']) }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="text-muted text-center py-3">{{ __('analytics.no_events') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
