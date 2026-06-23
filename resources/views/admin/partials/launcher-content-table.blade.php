@if($items->isEmpty())
    <p class="text-muted mb-0">{{ __('messages.launcher_content.no_items') }}</p>
@else
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>{{ __('messages.common.status') }}</th>
                    <th>{{ __('messages.launcher_content.sort_order') }}</th>
                    <th>{{ __('messages.launcher_content.item_title') }}</th>
                    <th>{{ __('messages.launcher_content.icon') }}</th>
                    <th>{{ __('messages.launcher_content.url') }}</th>
                    <th>{{ __('messages.common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                <tr>
                    <td>
                        @if($item->active)
                            <span class="badge bg-success">{{ __('messages.common.enabled') }}</span>
                        @else
                            <span class="badge bg-secondary">{{ __('messages.common.disabled') }}</span>
                        @endif
                    </td>
                    <td>{{ $item->sort_order }}</td>
                    <td class="text-truncate" style="max-width:180px" title="{{ $item->title }}">{{ $item->title }}</td>
                    <td>
                        @if($item->icon)
                            <i class="bi {{ $item->icon }}"></i> <code class="small">{{ $item->icon }}</code>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-truncate small" style="max-width:120px" title="{{ $item->url }}">
                        {{ $item->url ? $item->url : '—' }}
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-primary"
                            title="{{ __('messages.common.edit') }}"
                            onclick='openEditModal(@json($item))'>
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form action="{{ route('admin.launcher-content.toggle', $item) }}" method="POST" class="d-inline">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn btn-sm {{ $item->active ? 'btn-outline-secondary' : 'btn-outline-success' }}"
                                title="{{ $item->active ? __('messages.common.disable') : __('messages.common.enable') }}">
                                <i class="bi bi-{{ $item->active ? 'pause-fill' : 'play-fill' }}"></i>
                            </button>
                        </form>
                        <form id="delete-form-{{ $item->id }}" action="{{ route('admin.launcher-content.destroy', $item) }}" method="POST" class="d-inline">
                            @csrf @method('DELETE')
                            <button type="button" class="btn btn-sm btn-outline-danger"
                                title="{{ __('messages.common.delete') }}"
                                onclick="confirmDelete('delete-form-{{ $item->id }}')">
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
