@extends('dashboard.layout')
@section('title', __('messages.dashboard.not_found_title'))
@section('description', __('messages.dashboard.not_found_text'))
@section('robots', 'noindex')
@section('content')
<div class="empty" style="margin-top:24px">
    <strong>{{ __('messages.dashboard.not_found_title') }}@if($name !== '') : {{ $name }}@endif</strong>
    {{ __('messages.dashboard.not_found_text') }}
    <p style="margin-top:14px"><a href="{{ route('players.index') }}">&larr; {{ __('messages.dashboard.back') }}</a></p>
</div>
@endsection
