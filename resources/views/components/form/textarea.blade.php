@props(['name', 'label', 'value' => '', 'required' => false, 'hint' => null])
<div class="form-field">
    <label for="{{ $name }}" class="form-label">{{ $label }} @if($required)<span class="required-mark" aria-hidden="true">*</span>@endif</label>
    <textarea {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($name)])->merge(['rows' => 3]) }} id="{{ $name }}" name="{{ $name }}" @required($required) @if($errors->has($name))aria-invalid="true" aria-describedby="{{ $name }}_error"@endif>{{ old($name, $value) }}</textarea>
    @error($name)<div id="{{ $name }}_error" class="invalid-feedback">{{ $message }}</div>@enderror
    @if($hint)<div class="form-text">{{ $hint }}</div>@endif
</div>
