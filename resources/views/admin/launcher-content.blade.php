@extends('layouts.admin')

@section('title', __('messages.launcher_content.title'))

@section('page-title', __('messages.launcher_content.title'))

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row">
    {{-- Formulaire d'ajout --}}
    <div class="col-md-5 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header fw-semibold">
                <i class="bi bi-plus-circle me-1"></i> {{ __('messages.launcher_content.add') }}
            </div>
            <div class="card-body">
                <form action="{{ route('admin.launcher-content.store') }}" method="POST">
                    @csrf
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
                        <label class="form-label fw-semibold">{{ __('messages.launcher_content.section') }}</label>
                        <select name="section" class="form-select" required>
                            <option value="news_banner">{{ __('messages.launcher_content.section_news_banner') }}</option>
                            <option value="shortcut">{{ __('messages.launcher_content.section_shortcut') }}</option>
                            <option value="discover">{{ __('messages.launcher_content.section_discover') }}</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('messages.launcher_content.item_title') }}</label>
                        <input type="text" name="title" class="form-control" maxlength="255" required
                            placeholder="{{ __('messages.launcher_content.item_title') }}" value="{{ old('title') }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            {{ __('messages.launcher_content.description') }}
                            <span class="text-muted fw-normal">({{ __('messages.common.none') }})</span>
                        </label>
                        <textarea name="description" class="form-control" rows="3"
                            placeholder="{{ __('messages.launcher_content.description') }}">{{ old('description') }}</textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            {{ __('messages.launcher_content.icon') }}
                            <span class="text-muted fw-normal">({{ __('messages.common.none') }})</span>
                        </label>
                        <input type="text" name="icon" class="form-control" placeholder="bi-star" value="{{ old('icon') }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            {{ __('messages.launcher_content.image_url') }}
                            <span class="text-muted fw-normal">({{ __('messages.common.none') }})</span>
                        </label>
                        <input type="url" name="image_url" class="form-control" placeholder="https://..." value="{{ old('image_url') }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            {{ __('messages.launcher_content.url') }}
                            <span class="text-muted fw-normal">({{ __('messages.common.none') }})</span>
                        </label>
                        <input type="url" name="url" class="form-control" placeholder="https://..." value="{{ old('url') }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('messages.launcher_content.sort_order') }}</label>
                        <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', 0) }}">
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-send me-1"></i> {{ __('messages.common.add') }}
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Contenu par onglets --}}
    <div class="col-md-7 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header fw-semibold">
                <i class="bi bi-layout-text-window-reverse me-1"></i> {{ __('messages.launcher_content.title') }}
            </div>
            <div class="card-body">
                <ul class="nav nav-tabs" id="contentTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="news-tab" data-bs-toggle="tab" data-bs-target="#news-pane" type="button" role="tab">
                            {{ __('messages.launcher_content.tab_news') }}
                            <span class="badge bg-primary ms-1">{{ $newsBanners->count() }}</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="shortcuts-tab" data-bs-toggle="tab" data-bs-target="#shortcuts-pane" type="button" role="tab">
                            {{ __('messages.launcher_content.tab_shortcuts') }}
                            <span class="badge bg-primary ms-1">{{ $shortcuts->count() }}</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="discover-tab" data-bs-toggle="tab" data-bs-target="#discover-pane" type="button" role="tab">
                            {{ __('messages.launcher_content.tab_discover') }}
                            <span class="badge bg-primary ms-1">{{ $discover->count() }}</span>
                        </button>
                    </li>
                </ul>

                <div class="tab-content pt-3" id="contentTabsContent">
                    {{-- News Banners --}}
                    <div class="tab-pane fade show active" id="news-pane" role="tabpanel">
                        @include('admin.partials.launcher-content-table', ['items' => $newsBanners])
                    </div>
                    {{-- Shortcuts --}}
                    <div class="tab-pane fade" id="shortcuts-pane" role="tabpanel">
                        @include('admin.partials.launcher-content-table', ['items' => $shortcuts])
                    </div>
                    {{-- Discover --}}
                    <div class="tab-pane fade" id="discover-pane" role="tabpanel">
                        @include('admin.partials.launcher-content-table', ['items' => $discover])
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal d'édition --}}
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('messages.common.edit') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('messages.launcher_content.section') }}</label>
                        <select name="section" id="edit-section" class="form-select" required>
                            <option value="news_banner">{{ __('messages.launcher_content.section_news_banner') }}</option>
                            <option value="shortcut">{{ __('messages.launcher_content.section_shortcut') }}</option>
                            <option value="discover">{{ __('messages.launcher_content.section_discover') }}</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('messages.launcher_content.item_title') }}</label>
                        <input type="text" name="title" id="edit-title" class="form-control" maxlength="255" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('messages.launcher_content.description') }}</label>
                        <textarea name="description" id="edit-description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('messages.launcher_content.icon') }}</label>
                        <input type="text" name="icon" id="edit-icon" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('messages.launcher_content.image_url') }}</label>
                        <input type="url" name="image_url" id="edit-image_url" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('messages.launcher_content.url') }}</label>
                        <input type="url" name="url" id="edit-url" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('messages.launcher_content.sort_order') }}</label>
                        <input type="number" name="sort_order" id="edit-sort_order" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('messages.common.cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('messages.common.save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function openEditModal(item) {
        document.getElementById('editForm').action = '{{ url("admin/launcher-content") }}/' + item.id;
        document.getElementById('edit-section').value = item.section;
        document.getElementById('edit-title').value = item.title;
        document.getElementById('edit-description').value = item.description || '';
        document.getElementById('edit-icon').value = item.icon || '';
        document.getElementById('edit-image_url').value = item.image_url || '';
        document.getElementById('edit-url').value = item.url || '';
        document.getElementById('edit-sort_order').value = item.sort_order || 0;
        new bootstrap.Modal(document.getElementById('editModal')).show();
    }

    function confirmDelete(formId) {
        Swal.fire({
            title: '{{ __('messages.common.confirm_delete') }}',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: '{{ __('messages.common.delete') }}',
            cancelButtonText: '{{ __('messages.common.cancel') }}'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById(formId).submit();
            }
        });
    }
</script>
@endsection
