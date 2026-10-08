@extends('admin.analytics.layout')

@section('an')
@include('admin.analytics._kpi', ['kpi' => $kpi])
@include('admin.analytics._charts', ['charts' => $charts])
@endsection
