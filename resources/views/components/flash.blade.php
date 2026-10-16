{{-- Messages for this page, shown as toasts by resources/js/alerts.js: success, error, and a nudge when a form came back with problems. --}}
@if (session('status'))
    <template data-flash data-type="success" data-message="{{ session('status') }}"></template>
@endif
@if (session('error'))
    <template data-flash data-type="error" data-message="{{ session('error') }}"></template>
@endif
@if ($errors->any() && ! session('error'))
    <template data-flash data-type="error" data-message="{{ $errors->count() === 1 ? $errors->first() : 'Please fix the '.$errors->count().' highlighted fields.' }}"></template>
@endif
