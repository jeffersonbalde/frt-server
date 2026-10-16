@props(['name'])

@error($name)
    <p data-field-error="{{ $name }}" class="mt-1 text-sm text-red-600">{{ $message }}</p>
@enderror
