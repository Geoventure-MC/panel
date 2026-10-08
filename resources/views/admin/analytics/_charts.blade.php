<div class="row">
    @foreach ($charts as $ch)
        @include('admin.analytics._chart', $ch)
    @endforeach
</div>
