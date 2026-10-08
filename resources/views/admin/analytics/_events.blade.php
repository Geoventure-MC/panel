<div class="table-responsive">
    <table class="table table-sm an-table align-middle">
        <thead><tr><th>{{ __('analytics.date') }}</th><th>{{ __('analytics.type') }}</th><th>{{ __('analytics.country') }}</th><th>{{ __('analytics.detail') }}</th></tr></thead>
        <tbody>
        @forelse ($events as $e)
            <tr data-event="{{ $e['type'] }}">
                <td class="text-nowrap">{{ \App\Services\GameAnalytics::fmtDate($e['ts']) }}</td>
                <td><span class="an-chip">{{ \App\Services\GameAnalytics::typeLabel($e['type']) }}</span></td>
                <td>@if ($e['ref'] !== ''){{ $e['ref'] }}@else — @endif</td>
                <td class="small text-muted">{{ \App\Services\GameAnalytics::summarize($e['data']) }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="text-muted text-center py-3">{{ __('analytics.no_events') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
