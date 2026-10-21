@php $G = \App\Services\GameAnalytics::class; @endphp
<div class="table-responsive">
    <table class="table table-sm mod-table align-middle" id="{{ $tid ?? 'mod-reports' }}">
        <thead><tr>
            <th>{{ __('moderation.date') }}</th><th>{{ __('moderation.target') }}</th><th>{{ __('moderation.reporter') }}</th>
            <th>{{ __('moderation.reason') }}</th><th>{{ __('moderation.state_h') }}</th><th>{{ __('moderation.handled_by') }}</th><th></th>
        </tr></thead>
        <tbody>
        @forelse ($list as $r)
            <tr data-report="{{ $r['id'] }}" data-status="{{ $r['status'] }}">
                <td class="text-nowrap">{{ $G::fmtDate($r['created_at'], true) }}</td>
                <td>
                    @if (\App\Services\GameModeration::validName($r['target_name']))
                        <a href="{{ route('admin.moderation.player', ['player' => $r['target_name']]) }}">{{ $r['target_name'] }}</a>
                    @else
                        {{ $r['target_name'] !== '' ? $r['target_name'] : '—' }}
                    @endif
                </td>
                <td>{{ $r['reporter_label'] }}</td>
                <td class="text-break">{{ $r['reason'] !== '' ? $r['reason'] : '—' }}</td>
                <td>@include('admin.moderation._chips', ['kind' => 'report', 'val' => $r['status'], 'label' => __('moderation.status.' . $r['status'])])</td>
                <td>{{ $r['handled_by'] !== '' ? $r['handled_by'] : '—' }}</td>
                <td><a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.moderation.report', ['id' => $r['id']]) }}" title="{{ __('moderation.open_detail') }}"><i class="bi bi-box-arrow-up-right"></i></a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-muted text-center py-3">{{ __('moderation.none') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
