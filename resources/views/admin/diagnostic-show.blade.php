@extends('layouts.admin')

@section('title', __('messages.diagnostics.title'))

@section('page-title', __('messages.diagnostics.report', ['id' => $report->id]))

@section('content')
<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="{{ route('admin.diagnostics') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> {{ __('messages.diagnostics.back') }}</a>
    <a href="{{ route('admin.diagnostics.download', $report) }}" class="btn btn-outline-primary"><i class="bi bi-download"></i> {{ __('messages.diagnostics.download') }}</a>
    <form action="{{ route('admin.diagnostics.toggle', $report) }}" method="POST">
        @csrf @method('PATCH')
        <button class="btn btn-outline-success"><i class="bi bi-check2"></i> {{ $report->handled ? __('messages.diagnostics.reopen') : __('messages.diagnostics.handled') }}</button>
    </form>
    <form action="{{ route('admin.diagnostics.destroy', $report) }}" method="POST" onsubmit="return confirm(@js(__('messages.diagnostics.confirm_delete')))">
        @csrf @method('DELETE')
        <button class="btn btn-outline-danger"><i class="bi bi-trash"></i> {{ __('messages.diagnostics.delete') }}</button>
    </form>
</div>
<p><strong>{{ $report->summary }}</strong><br>
<span class="text-muted">{{ $report->created_at->format('d/m/Y H:i') }} · {{ $report->launcher_version }} · {{ $report->os }} · {{ $report->instance }}</span></p>
<pre class="bg-dark text-light p-3 rounded small" style="max-height: 70vh; overflow: auto; white-space: pre-wrap;">{{ $report->report }}</pre>
@endsection
