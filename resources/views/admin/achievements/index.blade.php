@extends('layouts.admin')

@section('title', __('messages.achievements.title'))

@section('page-title', __('messages.achievements.header'))

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
                <i class="bi bi-trophy me-1"></i> <span id="form-title">{{ __('messages.achievements.add') }}</span>
            </div>
            <div class="card-body">
                <form id="achievement-form" action="{{ route('admin.achievements.store') }}" method="POST">
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
                        <label class="form-label fw-semibold">{{ __('messages.achievements.code') }}</label>
                        <input type="text" name="code" id="ach-code" class="form-control" maxlength="255" required
                            placeholder="first_launch">
                        <div class="form-text">{{ __('messages.achievements.code_hint') }}</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('messages.achievements.name') }}</label>
                        <input type="text" name="name" id="ach-name" class="form-control" maxlength="255" required
                            placeholder="{{ __('messages.achievements.name_placeholder') }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('messages.achievements.description') }}
                            <span class="text-muted fw-normal">({{ __('messages.common.none') }})</span>
                        </label>
                        <textarea name="description" id="ach-description" class="form-control" rows="2" maxlength="1000"
                            placeholder="{{ __('messages.achievements.description_placeholder') }}"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('messages.achievements.icon') }}
                            <span class="text-muted fw-normal">({{ __('messages.common.none') }})</span>
                        </label>
                        <input type="text" name="icon" id="ach-icon" class="form-control" maxlength="255"
                            placeholder="bi-trophy">
                        <div class="form-text">{{ __('messages.achievements.icon_hint') }}</div>
                    </div>

                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-semibold">{{ __('messages.achievements.points') }}</label>
                            <input type="number" name="points" id="ach-points" class="form-control" min="0" max="100000" value="10" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-semibold">{{ __('messages.achievements.category') }}
                                <span class="text-muted fw-normal">({{ __('messages.common.none') }})</span>
                            </label>
                            <input type="text" name="category" id="ach-category" class="form-control" maxlength="255"
                                placeholder="{{ __('messages.achievements.category_placeholder') }}">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('messages.achievements.condition_type') }}</label>
                        <select name="condition_type" id="ach-condition-type" class="form-select" required onchange="toggleConditionValue()">
                            <option value="manual">{{ __('messages.achievements.cond_manual') }}</option>
                            <option value="first_launch">{{ __('messages.achievements.cond_first_launch') }}</option>
                            <option value="launch_count">{{ __('messages.achievements.cond_launch_count') }}</option>
                            <option value="playtime_hours">{{ __('messages.achievements.cond_playtime_hours') }}</option>
                            <option value="instances_tried">{{ __('messages.achievements.cond_instances_tried') }}</option>
                        </select>
                        <div class="form-text">{{ __('messages.achievements.cond_manual_hint') }}</div>
                    </div>

                    <div class="mb-3" id="condition-value-wrapper" style="display:none">
                        <label class="form-label fw-semibold">{{ __('messages.achievements.condition_value') }}</label>
                        <input type="number" name="condition_value" id="ach-condition-value" class="form-control" min="0"
                            placeholder="{{ __('messages.achievements.condition_value_placeholder') }}">
                        <div class="form-text">{{ __('messages.achievements.condition_value_hint') }}</div>
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

    {{-- Liste des succès --}}
    <div class="col-md-7 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header fw-semibold">
                <i class="bi bi-trophy me-1"></i> {{ __('messages.achievements.list') }}
            </div>
            <div class="card-body p-0">
                @if($achievements->isEmpty())
                    <p class="text-muted p-3 mb-0">{{ __('messages.achievements.none') }}</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('messages.common.status') }}</th>
                                    <th>{{ __('messages.achievements.name') }}</th>
                                    <th>{{ __('messages.achievements.condition_type') }}</th>
                                    <th>{{ __('messages.achievements.points') }}</th>
                                    <th>{{ __('messages.common.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($achievements as $ach)
                                <tr>
                                    <td>
                                        @if($ach->active)
                                            <span class="badge bg-success">{{ __('messages.common.enabled') }}</span>
                                        @else
                                            <span class="badge bg-secondary">{{ __('messages.common.disabled') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($ach->icon && \Illuminate\Support\Str::startsWith($ach->icon, 'bi-'))
                                            <i class="bi {{ $ach->icon }} me-1"></i>
                                        @elseif($ach->icon)
                                            <img src="{{ $ach->icon }}" alt="" width="20" height="20" class="me-1 rounded">
                                        @endif
                                        {{ $ach->name }}
                                        @if($ach->category)
                                            <span class="badge bg-light text-dark ms-1">{{ $ach->category }}</span>
                                        @endif
                                        <div class="text-muted small"><code>{{ $ach->code }}</code></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info text-dark">{{ $ach->condition_type }}</span>
                                        @if(!is_null($ach->condition_value))
                                            <span class="text-muted small">≥ {{ $ach->condition_value }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $ach->points }}</td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary" title="{{ __('messages.common.edit') }}"
                                            onclick="editAchievement({{ $ach->id }}, {{ json_encode($ach->code) }}, {{ json_encode($ach->name) }}, {{ json_encode($ach->description) }}, {{ json_encode($ach->icon) }}, {{ json_encode($ach->points) }}, {{ json_encode($ach->category) }}, {{ json_encode($ach->condition_type) }}, {{ json_encode($ach->condition_value) }})">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form action="{{ route('admin.achievements.toggle', $ach) }}" method="POST" class="d-inline">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="btn btn-sm {{ $ach->active ? 'btn-outline-secondary' : 'btn-outline-success' }}"
                                                title="{{ $ach->active ? __('messages.common.disable') : __('messages.common.enable') }}">
                                                <i class="bi bi-{{ $ach->active ? 'pause-fill' : 'play-fill' }}"></i>
                                            </button>
                                        </form>
                                        <form action="{{ route('admin.achievements.destroy', $ach) }}" method="POST" class="d-inline"
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
function toggleConditionValue() {
    var type = document.getElementById('ach-condition-type').value;
    var needsValue = (type === 'launch_count' || type === 'playtime_hours' || type === 'instances_tried');
    document.getElementById('condition-value-wrapper').style.display = needsValue ? '' : 'none';
}

function editAchievement(id, code, name, description, icon, points, category, conditionType, conditionValue) {
    document.getElementById('achievement-form').action = '{{ url("admin/achievements") }}/' + id;
    document.getElementById('form-method').value = 'PUT';
    document.getElementById('ach-code').value = code;
    document.getElementById('ach-name').value = name;
    document.getElementById('ach-description').value = description || '';
    document.getElementById('ach-icon').value = icon || '';
    document.getElementById('ach-points').value = points;
    document.getElementById('ach-category').value = category || '';
    document.getElementById('ach-condition-type').value = conditionType;
    document.getElementById('ach-condition-value').value = (conditionValue === null ? '' : conditionValue);
    toggleConditionValue();
    document.getElementById('form-title').textContent = '{{ __('messages.achievements.edit') }}';
    document.getElementById('form-btn-text').textContent = '{{ __('messages.common.update') }}';
    document.getElementById('cancel-edit').classList.remove('d-none');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetForm() {
    document.getElementById('achievement-form').action = '{{ route('admin.achievements.store') }}';
    document.getElementById('form-method').value = 'POST';
    document.getElementById('ach-code').value = '';
    document.getElementById('ach-name').value = '';
    document.getElementById('ach-description').value = '';
    document.getElementById('ach-icon').value = '';
    document.getElementById('ach-points').value = '10';
    document.getElementById('ach-category').value = '';
    document.getElementById('ach-condition-type').value = 'manual';
    document.getElementById('ach-condition-value').value = '';
    toggleConditionValue();
    document.getElementById('form-title').textContent = '{{ __('messages.achievements.add') }}';
    document.getElementById('form-btn-text').textContent = '{{ __('messages.common.add') }}';
    document.getElementById('cancel-edit').classList.add('d-none');
}

toggleConditionValue();
</script>
@endsection
