@extends('layouts.admin')

@section('title', __('messages.rpc.title'))
@section('page-title', __('messages.rpc.title'))

@section('content')
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>{{ __('messages.common.errors_occurred') }}</strong>
            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-4">
    <div class="col-lg-7">
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form action="{{ route('admin.rpc.update') }}" method="POST">
                @csrf

                <fieldset class="border p-3 mb-4 rounded">
                    <legend class="float-none w-auto px-2">{{ __('messages.rpc.activation') }}</legend>
                    <div class="form-check form-switch mb-2">
                        <input type="hidden" name="rpc_activation" value="0">
                        <input type="checkbox" id="rpc_activation" name="rpc_activation" class="form-check-input" value="1"
                               {{ old('rpc_activation', optional($rpcOptions)->rpc_activation) ? 'checked' : '' }}>
                        <label for="rpc_activation" class="form-check-label">{{ __('messages.rpc.enable_rpc') }}</label>
                    </div>
                </fieldset>

                <fieldset class="border p-3 mb-4 rounded">
                    <legend class="float-none w-auto px-2">{{ __('messages.rpc.general_info') }}</legend>

                    <div class="mb-3">
                        <label for="rpc_id" class="form-label">{{ __('messages.rpc.client_id') }}</label>
                        <input type="text" class="form-control" id="rpc_id" name="rpc_id"
                               value="{{ old('rpc_id', optional($rpcOptions)->rpc_id) }}" required>
                    </div>

                    <div class="mb-3">
                        <label for="rpc_details" class="form-label">{{ __('messages.rpc.details') }}</label>
                        <input type="text" class="form-control" id="rpc_details" name="rpc_details"
                               value="{{ old('rpc_details', optional($rpcOptions)->rpc_details) }}" required>
                    </div>

                    <div class="mb-3">
                        <label for="rpc_state" class="form-label">{{ __('messages.rpc.state') }}</label>
                        <input type="text" class="form-control" id="rpc_state" name="rpc_state"
                               value="{{ old('rpc_state', optional($rpcOptions)->rpc_state) }}" required>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="rpc_large_image" class="form-label">{{ __('messages.rpc.large_image') }}</label>
                            <input type="text" class="form-control" id="rpc_large_image" name="rpc_large_image"
                                   value="{{ old('rpc_large_image', optional($rpcOptions)->rpc_large_image) }}"
                                   placeholder="{{ __('messages.rpc.image_hint') }}">
                            <div class="form-text">{{ __('messages.rpc.image_hint') }}</div>
                        </div>
                        <div class="col-md-6">
                            <label for="rpc_large_text" class="form-label">{{ __('messages.rpc.large_image_text') }}</label>
                            <input type="text" class="form-control" id="rpc_large_text" name="rpc_large_text"
                                   value="{{ old('rpc_large_text', optional($rpcOptions)->rpc_large_text) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="rpc_small_image" class="form-label">{{ __('messages.rpc.small_image') }}</label>
                            <input type="text" class="form-control" id="rpc_small_image" name="rpc_small_image"
                                   value="{{ old('rpc_small_image', optional($rpcOptions)->rpc_small_image) }}"
                                   placeholder="{{ __('messages.rpc.image_hint') }}">
                            <div class="form-text">{{ __('messages.rpc.image_hint') }}</div>
                        </div>
                        <div class="col-md-6">
                            <label for="rpc_small_text" class="form-label">{{ __('messages.rpc.small_image_text') }}</label>
                            <input type="text" class="form-control" id="rpc_small_text" name="rpc_small_text"
                                   value="{{ old('rpc_small_text', optional($rpcOptions)->rpc_small_text) }}" required>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="border p-3 mb-4 rounded">
                    <legend class="float-none w-auto px-2">{{ __('messages.rpc.custom_buttons') }}</legend>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="rpc_button1" class="form-label">{{ __('messages.rpc.button1_name') }}</label>
                            <input type="text" class="form-control" id="rpc_button1" name="rpc_button1"
                                   value="{{ old('rpc_button1', optional($rpcOptions)->rpc_button1) }}">
                        </div>
                        <div class="col-md-6">
                            <label for="rpc_button1_url" class="form-label">{{ __('messages.rpc.button1_url') }}</label>
                            <input type="url" class="form-control" id="rpc_button1_url" name="rpc_button1_url"
                                   value="{{ old('rpc_button1_url', optional($rpcOptions)->rpc_button1_url) }}">
                        </div>

                        <div class="col-md-6">
                            <label for="rpc_button2" class="form-label">{{ __('messages.rpc.button2_name') }}</label>
                            <input type="text" class="form-control" id="rpc_button2" name="rpc_button2"
                                   value="{{ old('rpc_button2', optional($rpcOptions)->rpc_button2) }}">
                        </div>
                        <div class="col-md-6">
                            <label for="rpc_button2_url" class="form-label">{{ __('messages.rpc.button2_url') }}</label>
                            <input type="url" class="form-control" id="rpc_button2_url" name="rpc_button2_url"
                                   value="{{ old('rpc_button2_url', optional($rpcOptions)->rpc_button2_url) }}">
                        </div>
                    </div>
                </fieldset>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">💾 {{ __('messages.rpc.update_btn') }}</button>
                </div>
            </form>
        </div>
    </div>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm mb-4 position-lg-sticky" style="top: 1rem;">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-discord text-primary"></i>
                <span class="fw-semibold">{{ __('messages.rpc.preview_title') }}</span>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">{{ __('messages.rpc.preview_hint') }}</p>

                <div id="rpcPreviewCard" style="background:#232428;border-radius:10px;padding:16px;color:#fff;font-family:'gg sans','Segoe UI',Helvetica,Arial,sans-serif;max-width:380px;">
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.02em;color:#b5bac1;margin-bottom:10px;">{{ __('messages.rpc.preview_playing') }}</div>
                    <div style="display:flex;gap:12px;">
                        <div style="position:relative;flex:0 0 auto;">
                            <div id="rpcLargeImg" style="width:60px;height:60px;border-radius:8px;background:#1e1f22;display:flex;align-items:center;justify-content:center;overflow:hidden;font-size:9px;color:#6d6f78;text-align:center;padding:2px;"></div>
                            <div id="rpcSmallImg" style="position:absolute;right:-6px;bottom:-6px;width:24px;height:24px;border-radius:50%;border:3px solid #232428;background:#1e1f22;display:flex;align-items:center;justify-content:center;overflow:hidden;font-size:7px;color:#6d6f78;text-align:center;"></div>
                        </div>
                        <div style="min-width:0;flex:1;">
                            <div id="rpcAppName" style="font-size:14px;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"></div>
                            <div id="rpcDetails" style="font-size:13px;font-weight:600;color:#dbdee1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"></div>
                            <div id="rpcState" style="font-size:13px;color:#dbdee1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"></div>
                            <div id="rpcElapsed" style="font-size:13px;color:#dbdee1;">00:00 {{ __('messages.rpc.preview_elapsed') }}</div>
                        </div>
                    </div>
                    <div id="rpcButtons" style="margin-top:12px;display:flex;flex-direction:column;gap:8px;"></div>
                </div>
                <div id="rpcDisabledNote" class="alert alert-warning small mt-3 d-none mb-0">
                    <i class="bi bi-exclamation-triangle me-1"></i>{{ __('messages.rpc.preview_disabled') }}
                </div>
            </div>
        </div>
    </div>
    </div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    function esc(text) {
        const d = document.createElement('div');
        d.textContent = text == null ? '' : text;
        return d.innerHTML;
    }
    function isUrl(v) {
        return typeof v === 'string' && /^https?:\/\//i.test(v.trim());
    }
    function setImage(el, val) {
        if (!el) return;
        const v = (val || '').trim();
        if (isUrl(v)) {
            el.innerHTML = '<img src="' + esc(v) + '" alt="" style="width:100%;height:100%;object-fit:cover;" '
                + 'onerror="this.parentNode.textContent=\'IMG\';">';
        } else if (v) {
            el.textContent = v;
        } else {
            el.textContent = '';
        }
    }

    const f = {
        activation: document.getElementById('rpc_activation'),
        details:    document.getElementById('rpc_details'),
        state:      document.getElementById('rpc_state'),
        largeImg:   document.getElementById('rpc_large_image'),
        largeText:  document.getElementById('rpc_large_text'),
        smallImg:   document.getElementById('rpc_small_image'),
        smallText:  document.getElementById('rpc_small_text'),
        btn1:       document.getElementById('rpc_button1'),
        btn1Url:    document.getElementById('rpc_button1_url'),
        btn2:       document.getElementById('rpc_button2'),
        btn2Url:    document.getElementById('rpc_button2_url'),
    };

    const pAppName  = document.getElementById('rpcAppName');
    const pDetails  = document.getElementById('rpcDetails');
    const pState    = document.getElementById('rpcState');
    const pLarge    = document.getElementById('rpcLargeImg');
    const pSmall    = document.getElementById('rpcSmallImg');
    const pButtons  = document.getElementById('rpcButtons');
    const pCard     = document.getElementById('rpcPreviewCard');
    const pDisabled = document.getElementById('rpcDisabledNote');
    const APP_FALLBACK = @json(__('messages.rpc.preview_app_fallback'));

    function render() {
        const details = (f.details && f.details.value) || '';
        pAppName.textContent = details.trim() !== '' ? details : APP_FALLBACK;
        pDetails.textContent = details;
        pState.textContent   = (f.state && f.state.value) || '';

        setImage(pLarge, f.largeImg ? f.largeImg.value : '');
        setImage(pSmall, f.smallImg ? f.smallImg.value : '');
        if (f.largeImg) pLarge.title = (f.largeText && f.largeText.value) || '';
        if (f.smallImg) pSmall.title = (f.smallText && f.smallText.value) || '';

        const buttons = [
            { label: f.btn1 ? f.btn1.value : '', url: f.btn1Url ? f.btn1Url.value : '' },
            { label: f.btn2 ? f.btn2.value : '', url: f.btn2Url ? f.btn2Url.value : '' },
        ].filter(b => (b.label || '').trim() !== '');

        pButtons.innerHTML = buttons.map(b =>
            '<div style="background:#4e5058;color:#fff;border-radius:4px;padding:8px 12px;font-size:13px;font-weight:600;text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">'
            + esc(b.label) + '</div>'
        ).join('');

        const enabled = !f.activation || f.activation.checked;
        pCard.style.opacity = enabled ? '1' : '0.5';
        if (pDisabled) pDisabled.classList.toggle('d-none', enabled);
    }

    Object.values(f).forEach(el => {
        if (!el) return;
        el.addEventListener('input', render);
        el.addEventListener('change', render);
    });
    render();
});
</script>
@endsection
