@php $G = \App\Services\GameAnalytics::class; @endphp
<div class="table-responsive">
    <table class="table table-sm mod-table align-middle" id="{{ $tid ?? 'mod-sanctions' }}">
        <thead><tr>
            <th>{{ __('moderation.date') }}</th><th>{{ __('moderation.type_h') }}</th><th>{{ __('moderation.player') }}</th>
            <th>{{ __('moderation.reason') }}</th><th>{{ __('moderation.staff') }}</th><th>{{ __('moderation.expires') }}</th><th>{{ __('moderation.state_h') }}</th><th></th>
        </tr></thead>
        <tbody>
        @forelse ($list as $s)
            <tr data-sanction="{{ $s['id'] }}" data-type="{{ $s['type'] }}" data-state="{{ $s['state'] }}">
                <td class="text-nowrap">{{ $G::fmtDate($s['created_at'], true) }}</td>
                <td>@include('admin.moderation._chips', ['kind' => 'type', 'val' => $s['type'], 'label' => $s['type_label']])</td>
                <td>
                    @if (\App\Services\GameModeration::validName($s['player_label']))
                        <a href="{{ route('admin.moderation.player', ['player' => $s['player_label']]) }}">{{ $s['player_label'] }}</a>
                    @else
                        {{ $s['player_label'] }}
                    @endif
                </td>
                <td class="text-break">{{ $s['reason'] !== '' ? $s['reason'] : '—' }}</td>
                <td>{{ $s['staff_name'] !== '' ? $s['staff_name'] : '—' }}</td>
                <td class="text-nowrap">{{ $s['expires_at'] ? $G::fmtDate($s['expires_at'], true) : __('moderation.permanent') }}</td>
                <td>@include('admin.moderation._chips', ['kind' => 'state', 'val' => $s['state'], 'label' => __('moderation.state.' . $s['state'])])</td>
                <td><a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.moderation.sanction', ['id' => $s['id']]) }}" title="{{ __('moderation.open_detail') }}"><i class="bi bi-box-arrow-up-right"></i></a></td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-muted text-center py-3">{{ __('moderation.none') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
