@php
    $type = $field['type'] ?? 'text';
    $name = $field['name'];
    $value = old($name, $field['value'] ?? ($type === 'checkbox' ? false : null));
    $required = (bool)($field['required'] ?? false);
    $readonly = (bool)($field['readonly'] ?? false);
    $options = $field['options'] ?? [];
@endphp
@if($type === 'hidden')
    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
@elseif($type === 'checkbox')
    <label class="form-check form-switch mt-4"><input type="hidden" name="{{ $name }}" value="0"><input class="form-check-input" type="checkbox" name="{{ $name }}" value="1" @checked((bool)$value)><span class="form-check-label">{{ $field['label'] }}</span></label>
@else
    <label class="form-label">{{ $field['label'] }} @if($required)<span class="text-danger">*</span>@endif</label>
    @if($type === 'textarea')
        <textarea name="{{ $name }}" class="form-control @error($name) is-invalid @enderror" rows="{{ $field['rows'] ?? 4 }}" @required($required) @readonly($readonly)>{{ $value }}</textarea>
    @elseif($type === 'select')
        <select name="{{ $name }}" class="form-select @error($name) is-invalid @enderror" @required($required)>
            @foreach($options as $optionValue => $optionLabel)<option value="{{ $optionValue }}" @selected((string)$value === (string)$optionValue)>{{ $optionLabel }}</option>@endforeach
        </select>
    @elseif($type === 'multiselect')
        @php($selected = collect(is_array($value) ? $value : (blank($value) ? [] : [$value]))->map(fn($v)=>(string)$v)->all())
        <select name="{{ $name }}[]" class="form-select @error($name) is-invalid @enderror" multiple size="{{ min(10,max(4,count($options))) }}" @required($required)>
            @foreach($options as $optionValue => $optionLabel)<option value="{{ $optionValue }}" @selected(in_array((string)$optionValue,$selected,true))>{{ $optionLabel }}</option>@endforeach
        </select>
        <div class="form-hint">Use Ctrl/Command to select multiple values.</div>
    @elseif($type === 'file')
        <input type="file" name="{{ $name }}" class="form-control @error($name) is-invalid @enderror" @required($required)>
    @else
        <input type="{{ $type }}" name="{{ $name }}" value="{{ $value }}" class="form-control @error($name) is-invalid @enderror" @if(isset($field['step'])) step="{{ $field['step'] }}" @endif @if(isset($field['min'])) min="{{ $field['min'] }}" @endif @required($required) @readonly($readonly)>
    @endif
    @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
@endif
