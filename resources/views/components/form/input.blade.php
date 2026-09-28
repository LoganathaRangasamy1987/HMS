@props(['name', 'label', 'type' => 'text', 'value' => '', 'required' => false, 'hint' => null])
@php($id = str_replace(['.', '[', ']'], ['_', '_', ''], $name))
<div class="form-field">
    <label for="{{ $id }}" class="form-label">{{ $label }} @if($required)<span class="required-mark" aria-hidden="true">*</span>@endif</label>
    <input {{ $attributes->class(['form-control', 'is-invalid' => $errors->has($name)]) }} id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" @if(!in_array($type, ['password', 'file']))value="{{ old($name, $value) }}"@endif @required($required) @if($errors->has($name))aria-invalid="true" aria-describedby="{{ $id }}_error"@elseif($hint)aria-describedby="{{ $id }}_hint"@endif>
    @error($name)<div id="{{ $id }}_error" class="invalid-feedback">{{ $message }}</div>@enderror
    @if($hint)<div id="{{ $id }}_hint" class="form-text">{{ $hint }}</div>@endif
</div>
