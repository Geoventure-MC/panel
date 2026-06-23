@extends('layouts.admin')

@section('title', __('messages.mods.title'))

@section('content')
<div class="container-fluid px-0">
    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <h2 class="mb-4 fw-bold">{{ __('messages.mods.header') }}</h2>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="mb-4">
                <label for="optionalMods" class="form-label">{{ __('messages.mods.select_mod') }}</label>
                <select id="optionalMods" name="selectedMod" class="form-select" onchange="handleSelectChange()">
                    <option value="">{{ __('messages.mods.select_placeholder') }}</option>
                    @foreach ($optionalMods as $mod)
                        <option value="{{ $mod->id }}" {{ old('selectedMod', $selectedModId) == $mod->id ? 'selected' : '' }}>
                            {{ $mod->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div id="modDetails" class="{{ $selectedModId ? '' : 'd-none' }}">
                <h4 class="mb-3 fw-semibold">{{ __('messages.mods.mod_details') }}</h4>

                <form method="POST" action="{{ route('admin.mods.updateOptional') }}" enctype="multipart/form-data" id="modForm" onsubmit="return validateForm()">
                    @csrf
                    <input type="hidden" name="mod_id" id="mod_id" value="{{ $selectedModId ?? '' }}">

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ __('messages.mods.mod_file') }}</label>
                            <input type="text" id="mod_file" class="form-control" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('messages.mods.mod_name') }}</label>
                            <input type="text" name="optional_name" id="optional_name" class="form-control">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('messages.mods.description') }}</label>
                        <textarea name="optional_description" id="optional_description" class="form-control" rows="3"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('messages.mods.current_image') }}</label><br>
                        <img id="current_image" src="" alt="" class="rounded d-none mb-2" style="height: 64px; width: 64px; object-fit: cover;">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('messages.mods.new_image') }}</label>
                        <input type="file" name="optional_image" accept="image/jpeg,image/png,image/gif" class="form-control">
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input type="checkbox" name="optional_recommended" value="1" id="optional_recommended" class="form-check-input">
                        <label class="form-check-label" for="optional_recommended">{{ __('messages.mods.recommended') }}</label>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('messages.mods.instance') }}</label>
                        <select name="optional_instance" id="optional_instance" class="form-select">
                            <option value="">{{ __('messages.mods.instance_all') }}</option>
                            @foreach ($instances as $inst)
                                <option value="{{ $inst['slug'] }}">{{ $inst['name'] }} ({{ $inst['slug'] }})</option>
                            @endforeach
                        </select>
                        <small class="text-muted">{{ __('messages.mods.instance_hint') }}</small>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success">{{ __('messages.mods.modify') }}</button>
                        <button type="button" id="deleteBtn" class="btn btn-outline-danger" onclick="deleteMod()">{{ __('messages.common.delete') }}</button>
                    </div>
                </form>
            </div>

            <h4 class="mt-5 mb-3 fw-semibold">{{ __('messages.mods.available_mods') }}</h4>

            <ul class="list-group">
                @foreach ($modsData as $mod)
                    @if (!in_array($mod['file'], $optionalMods->pluck('file')->toArray()))
                        <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap">
                            <div class="me-auto">{{ $mod['name'] }}</div>
                            <form method="POST" action="{{ route('admin.mods.addOptional') }}" enctype="multipart/form-data" class="ms-auto d-flex align-items-center gap-2">
                                @csrf
                                <input type="hidden" name="file" value="{{ $mod['file'] }}">
                                <input type="hidden" name="name" value="{{ $mod['name'] }}">
                                <input type="hidden" name="description" value="{{ $mod['description'] }}">
                                <input type="hidden" name="icon" value="{{ $mod['icon'] }}">
                                @if($instances->isNotEmpty())
                                    <select name="instance" class="form-select form-select-sm" style="width:auto;" title="{{ __('messages.mods.instance') }}">
                                        <option value="">{{ __('messages.mods.instance_all') }}</option>
                                        @foreach ($instances as $inst)
                                            <option value="{{ $inst['slug'] }}">{{ $inst['slug'] }}</option>
                                        @endforeach
                                    </select>
                                @endif
                                <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('messages.mods.add_optional') }}</button>
                            </form>
                        </li>
                    @endif
                @endforeach
            </ul>
        </div>
    </div>
</div>

<script>
    const optionalModsSelect = document.getElementById('optionalMods');
    const modDetails = document.getElementById('modDetails');
    const modIdInput = document.getElementById('mod_id');
    const fileInput = document.getElementById('mod_file');
    const nameInput = document.getElementById('optional_name');
    const descriptionInput = document.getElementById('optional_description');
    const currentImage = document.getElementById('current_image');
    const recommendedCheckbox = document.getElementById('optional_recommended');
    const instanceSelect = document.getElementById('optional_instance');

    function handleSelectChange() {
        const selectedModId = optionalModsSelect.value;

        if (selectedModId) {
            fetch(`/admin/mods/${selectedModId}`)
                .then(response => response.json())
                .then(data => {
                    modIdInput.value = data.id;
                    fileInput.value = data.file;
                    nameInput.value = data.name;
                    descriptionInput.value = data.description;
                    currentImage.src = '/storage/' + data.icon;
                    currentImage.classList.remove('d-none');
                    recommendedCheckbox.checked = data.recommended;
                    if (instanceSelect) instanceSelect.value = data.instance || '';

                    modDetails.classList.remove('d-none');
                })
                .catch(error => console.error('{{ __('messages.common.error') }}:', error));
        } else {
            modIdInput.value = '';
            fileInput.value = '';
            nameInput.value = '';
            descriptionInput.value = '';
            currentImage.src = '';
            currentImage.classList.add('d-none');
            recommendedCheckbox.checked = false;
            if (instanceSelect) instanceSelect.value = '';
            modDetails.classList.add('d-none');
        }
    }

    function validateForm() {
        if (!optionalModsSelect.value) {
            Swal.fire('{{ __('messages.common.error') }}', '{{ __('messages.mods.error_select_edit') }}', 'error');
            return false;
        }
        return true;
    }

    function deleteMod() {
        const selectedModId = optionalModsSelect.value;
        if (!selectedModId) {
            Swal.fire('{{ __('messages.common.error') }}', '{{ __('messages.mods.error_select_delete') }}', 'error');
            return;
        }

        Swal.fire({
            title: '{{ __('messages.mods.confirm_delete') }}',
            text: "{{ __('messages.mods.delete_warning') }}",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: '{{ __('messages.mods.yes_delete') }}',
            cancelButtonText: '{{ __('messages.common.cancel') }}'
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `/admin/mods/delete/${selectedModId}`;

                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = '_token';
                csrfInput.value = '{{ csrf_token() }}';

                form.appendChild(csrfInput);
                document.body.appendChild(form);
                form.submit();
            }
        });
    }
</script>
@endsection
