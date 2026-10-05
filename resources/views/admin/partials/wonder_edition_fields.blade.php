<div class="row g-2">
    <div class="col-md-6"><label class="form-label small">{{ __('messages.wonder.name') }}</label>
        <input name="name" value="{{ $f['name'] }}" maxlength="40" required class="form-control form-control-sm"></div>
    <div class="col-md-6"><label class="form-label small">{{ __('messages.wonder.theme') }}</label>
        <input name="theme" value="{{ $f['theme'] }}" maxlength="80" required class="form-control form-control-sm"></div>
    <div class="col-md-4"><label class="form-label small">{{ __('messages.wonder.world') }}</label>
        <input name="world" value="{{ $f['world'] }}" maxlength="32" required class="form-control form-control-sm"></div>
    <div class="col-md-4"><label class="form-label small">{{ __('messages.wonder.opens_at') }}</label>
        <input type="datetime-local" name="opens_at" value="{{ $f['opens_at'] }}" required class="form-control form-control-sm"></div>
    <div class="col-md-4"><label class="form-label small">{{ __('messages.wonder.closes_at') }}</label>
        <input type="datetime-local" name="closes_at" value="{{ $f['closes_at'] }}" required class="form-control form-control-sm"></div>
    <div class="col-6 col-md-3"><label class="form-label small">{{ __('messages.wonder.team_size') }}</label>
        <input type="number" name="team_size" value="{{ $f['team_size'] }}" min="1" max="60" required class="form-control form-control-sm"></div>
    <div class="col-6 col-md-3"><label class="form-label small">{{ __('messages.wonder.builders') }}</label>
        <input type="number" name="builders" value="{{ $f['builders'] }}" min="1" max="10" required class="form-control form-control-sm"></div>
    <div class="col-12"><label class="form-label small">{{ __('messages.wonder.weights') }}</label>
        <div class="row g-2">
            <div class="col-4"><input type="number" name="weight_technical" value="{{ $f['wt'] }}" min="0" max="100" required class="form-control form-control-sm" title="{{ __('messages.wonder.w_technical') }}"></div>
            <div class="col-4"><input type="number" name="weight_aesthetic" value="{{ $f['wa'] }}" min="0" max="100" required class="form-control form-control-sm" title="{{ __('messages.wonder.w_aesthetic') }}"></div>
            <div class="col-4"><input type="number" name="weight_theme" value="{{ $f['wh'] }}" min="0" max="100" required class="form-control form-control-sm" title="{{ __('messages.wonder.w_theme') }}"></div>
        </div>
        <div class="form-text">{{ __('messages.wonder.w_technical') }} / {{ __('messages.wonder.w_aesthetic') }} / {{ __('messages.wonder.w_theme') }}</div>
    </div>
    <div class="col-12"><label class="form-label small">{{ __('messages.wonder.rewards') }}</label>
        <textarea name="rewards" rows="2" maxlength="1000" class="form-control form-control-sm">{{ $f['rewards'] }}</textarea></div>
</div>
