@props(['name', 'label', 'value' => '', 'options' => [], 'required' => false, 'placeholder' => null, 'hint' => null])
@php($id = str_replace(['.', '[', ']'], ['_', '_', ''], $name))
<div class="form-field">
    <label for="{{ $id }}" class="form-label">{{ $label }} @if($required)<span class="required-mark" aria-hidden="true">*</span>@endif</label>
    <select {{ $attributes->class(['form-select', 'is-invalid' => $errors->has($name)]) }} id="{{ $id }}" name="{{ $name }}" @required($required) @if($errors->has($name))aria-invalid="true" aria-describedby="{{ $id }}_error"@endif>
        @if($placeholder)<option value="">{{ $placeholder }}</option>@endif
        @foreach($options as $key => $text)<option value="{{ $key }}" @selected((string) old($name, $value) === (string) $key)>{{ $text }}</option>@endforeach
    </select>
    @error($name)<div id="{{ $id }}_error" class="invalid-feedback">{{ $message }}</div>@enderror
    @if($hint)<div class="form-text">{{ $hint }}</div>@endif
</div>
