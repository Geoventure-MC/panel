@extends('admin.analytics.layout')

@section('an')
<div class="row" id="an-rankings">
    @forelse ($boards as $b)
        <div class="col-12 col-md-6 col-xl-4 mb-4"><div class="card shadow-sm border-0 h-100"><div class="card-body">
            <h6 class="card-title">{{ $b['title'] }}</h6>
            <table class="table table-sm an-table mb-0"><tbody>
                @foreach ($b['rows'] as $i => $row)<tr><td class="text-muted" style="width:2rem">{{ $i + 1 }}</td><td>{{ $row['name'] }}</td><td class="num">{{ $row['value'] }}</td></tr>@endforeach
            </tbody></table>
        </div></div></div>
    @empty
        <div class="col-12"><div class="card shadow-sm border-0"><div class="card-body text-center text-muted py-4">{{ __('analytics.rankings_empty') }}</div></div></div>
    @endforelse
</div>
@endsection
