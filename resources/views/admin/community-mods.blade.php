@extends('layouts.admin')

@section('title', __('messages.community_mods.title'))

@section('page-title', __('messages.community_mods.header'))

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row">
    {{-- Formulaire d'ajout / édition --}}
    <div class="col-md-5 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header fw-semibold">
                <i class="bi bi-plus-circle me-1"></i> <span id="form-title">{{ __('messages.community_mods.add') }}</span>
            </div>
            <div class="card-body">
                <form id="mod-form" action="{{ route('admin.community-mods.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="_method" value="POST" id="form-method">
                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->all() as $e)
                                    <li>{{ $e }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('messages.community_mods.name') }}</label>
                        <input type="text" name="name" id="mod-name" class="form-control" maxlength="255" required
                            placeholder="{{ __('messages.community_mods.name_placeholder') }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('messages.community_mods.description') }}
                            <span class="text-muted fw-normal">({{ __('messages.common.none') }})</span>
                        </label>
                        <textarea name="description" id="mod-description" class="form-control" rows="3" maxlength="1000"
                            placeholder="{{ __('messages.community_mods.description_placeholder') }}"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('messages.community_mods.filename') }}</label>
                        <input type="text" name="filename" id="mod-filename" class="form-control" maxlength="255" required
                            placeholder="mon-mod-1.0.jar">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('messages.community_mods.url') }}</label>
                        <input type="url" name="url" id="mod-url" class="form-control" maxlength="500" required
                            placeholder="https://example.com/mods/mon-mod-1.0.jar">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('messages.community_mods.icon') }}
                            <span class="text-muted fw-normal">({{ __('messages.common.none') }})</span>
                        </label>
                        <input type="url" name="icon" id="mod-icon" class="form-control" maxlength="500"
                            placeholder="https://example.com/icons/mon-mod.png">
                    </div>

                    <div class="row">
                        <div class="col-4 mb-3">
                            <label class="form-label fw-semibold">{{ __('messages.community_mods.category') }}</label>
                            <input type="text" name="category" id="mod-category" class="form-control" maxlength="100"
                                placeholder="{{ __('messages.community_mods.category_placeholder') }}">
                        </div>
                        <div class="col-4 mb-3">
                            <label class="form-label fw-semibold">{{ __('messages.community_mods.author') }}</label>
                            <input type="text" name="author" id="mod-author" class="form-control" maxlength="100"
                                placeholder="{{ __('messages.community_mods.author_placeholder') }}">
                        </div>
                        <div class="col-4 mb-3">
                            <label class="form-label fw-semibold">{{ __('messages.community_mods.version') }}</label>
                            <input type="text" name="version" id="mod-version" class="form-control" maxlength="50"
                                placeholder="1.0.0">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-send me-1"></i> <span id="form-btn-text">{{ __('messages.common.add') }}</span>
                    </button>
                </form>
                <button type="button" class="btn btn-outline-secondary w-100 mt-2 d-none" id="cancel-edit" onclick="resetForm()">
                    <i class="bi bi-x-circle me-1"></i> {{ __('messages.common.cancel') }}
                </button>
            </div>
        </div>
    </div>

    {{-- Liste des mods communauté --}}
    <div class="col-md-7 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header fw-semibold">
                <i class="bi bi-puzzle me-1"></i> {{ __('messages.community_mods.list') }}
            </div>
            <div class="card-body p-0">
                @if($mods->isEmpty())
                    <p class="text-muted p-3 mb-0">{{ __('messages.community_mods.none') }}</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('messages.common.status') }}</th>
                                    <th>{{ __('messages.community_mods.name') }}</th>
                                    <th>{{ __('messages.community_mods.filename') }}</th>
                                    <th>{{ __('messages.community_mods.url') }}</th>
                                    <th>{{ __('messages.common.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($mods as $mod)
                                <tr>
                                    <td>
                                        @if($mod->active)
                                            <span class="badge bg-success">{{ __('messages.common.enabled') }}</span>
                                        @else
                                            <span class="badge bg-secondary">{{ __('messages.common.disabled') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($mod->icon)
                                            <img src="{{ $mod->icon }}" alt="" width="20" height="20" class="me-1 rounded">
                                        @endif
                                        {{ $mod->name }}
                                    </td>
                                    <td class="text-truncate" style="max-width:150px" title="{{ $mod->filename }}">{{ $mod->filename }}</td>
                                    <td class="text-truncate" style="max-width:150px">
                                        <a href="{{ $mod->url }}" target="_blank" rel="noopener" title="{{ $mod->url }}">{{ $mod->url }}</a>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary" title="{{ __('messages.common.edit') }}"
                                            onclick="editMod({{ $mod->id }}, {{ json_encode($mod->name) }}, {{ json_encode($mod->description) }}, {{ json_encode($mod->filename) }}, {{ json_encode($mod->url) }}, {{ json_encode($mod->icon) }}, {{ json_encode($mod->category) }}, {{ json_encode($mod->author) }}, {{ json_encode($mod->version) }})">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form action="{{ route('admin.community-mods.toggle', $mod) }}" method="POST" class="d-inline">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="btn btn-sm {{ $mod->active ? 'btn-outline-secondary' : 'btn-outline-success' }}"
                                                title="{{ $mod->active ? __('messages.common.disable') : __('messages.common.enable') }}">
                                                <i class="bi bi-{{ $mod->active ? 'pause-fill' : 'play-fill' }}"></i>
                                            </button>
                                        </form>
                                        <form action="{{ route('admin.community-mods.destroy', $mod) }}" method="POST" class="d-inline"
                                            onsubmit="return confirm('{{ __('messages.common.confirm_delete') }}')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('messages.common.delete') }}">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function editMod(id, name, description, filename, url, icon, category, author, version) {
    document.getElementById('mod-form').action = '{{ url("admin/community-mods") }}/' + id;
    document.getElementById('form-method').value = 'PUT';
    document.getElementById('mod-name').value = name;
    document.getElementById('mod-description').value = description || '';
    document.getElementById('mod-filename').value = filename;
    document.getElementById('mod-url').value = url;
    document.getElementById('mod-icon').value = icon || '';
    document.getElementById('mod-category').value = category || '';
    document.getElementById('mod-author').value = author || '';
    document.getElementById('mod-version').value = version || '';
    document.getElementById('form-title').textContent = '{{ __('messages.community_mods.edit') }}';
    document.getElementById('form-btn-text').textContent = '{{ __('messages.common.update') }}';
    document.getElementById('cancel-edit').classList.remove('d-none');
}

function resetForm() {
    document.getElementById('mod-form').action = '{{ route('admin.community-mods.store') }}';
    document.getElementById('form-method').value = 'POST';
    document.getElementById('mod-name').value = '';
    document.getElementById('mod-description').value = '';
    document.getElementById('mod-filename').value = '';
    document.getElementById('mod-url').value = '';
    document.getElementById('mod-icon').value = '';
    document.getElementById('mod-category').value = '';
    document.getElementById('mod-author').value = '';
    document.getElementById('mod-version').value = '';
    document.getElementById('form-title').textContent = '{{ __('messages.community_mods.add') }}';
    document.getElementById('form-btn-text').textContent = '{{ __('messages.common.add') }}';
    document.getElementById('cancel-edit').classList.add('d-none');
}
</script>
@endsection
