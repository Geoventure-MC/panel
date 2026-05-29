@extends('layouts.admin')

@section('content')
<div class="container mt-4">
    <h1>{{ __('messages.update_page.header') }}</h1>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if(session('info'))
        <div class="alert alert-info">{{ session('info') }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <p>{{ __('messages.update_page.current_version') }} : <strong>{{ $currentVersion }}</strong></p>
            @if($info)
                <p>{{ __('messages.update_page.latest_version') }} : <strong>{{ $info['version'] ?? '?' }}</strong></p>
            @else
                <p>{{ __('messages.update_page.unable_fetch') }}</p>
            @endif
        </div>
    </div>

    @if($hasUpdate)
        <form method="POST" action="{{ route('admin.update.run') }}">
            @csrf
            <button type="submit" class="btn btn-primary">{{ __('messages.update_page.update_now') }}</button>
        </form>
    @else
        <div class="alert alert-info">{{ __('messages.update_page.no_update') }}</div>
    @endif
</div>
@endsection
