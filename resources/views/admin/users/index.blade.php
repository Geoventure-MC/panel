@extends('layouts.admin')

@section('title', __('messages.users.title'))
@section('page-title', __('messages.users.header'))
@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">{{ __('messages.users.list') }}</h3>
                    <div class="card-tools">
                        <a type="button" class="btn btn-primary" href="{{ route('admin.users.create') }}">
                            {{ __('messages.users.add_user') }}
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>{{ __('messages.users.id') }}</th>
                                <th>{{ __('messages.users.name') }}</th>
                                <th>{{ __('messages.users.email') }}</th>
                                <th>{{ __('messages.roles.label') }}</th>
                                <th>{{ __('messages.common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($users as $user)
                            <tr>
                                <td>{{ $user->id }}</td>
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td>
                                    @php $role = $user->isSuperAdmin() ? 'superadmin' : ($user->isModerator() ? 'moderator' : ($user->role ?? 'admin')); @endphp
                                    @if(auth()->user()->isSuperAdmin())
                                        <form action="{{ route('admin.users.role', $user->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('PUT')
                                            <select name="role" class="form-select form-select-sm d-inline-block w-auto" onchange="this.form.submit()">
                                                <option value="superadmin" {{ $role === 'superadmin' ? 'selected' : '' }}>{{ __('messages.roles.superadmin') }}</option>
                                                <option value="moderator" {{ $role === 'moderator' ? 'selected' : '' }}>{{ __('messages.roles.moderator') }}</option>
                                            </select>
                                        </form>
                                    @else
                                        <span class="badge bg-{{ $role === 'superadmin' ? 'danger' : 'secondary' }}">
                                            {{ $role === 'superadmin' ? __('messages.roles.superadmin') : __('messages.roles.moderator') }}
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('admin.users.edit', $user->id) }}" class="btn btn-sm btn-info">
                                        <i class="fas fa-edit"></i> {{ __('messages.common.edit') }}
                                    </a>
                                    <form action="{{ route('admin.users.delete', $user->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('{{ __('messages.users.confirm_delete_user') }}')">
                                            <i class="fas fa-trash"></i> {{ __('messages.common.delete') }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
