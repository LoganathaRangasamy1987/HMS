@if(session('success'))
    <div class="alert alert-success d-flex align-items-start gap-2" role="status"><x-icon name="check"/><div>{{ session('success') }}</div></div>
@endif
@if(session('status'))
    <div class="alert alert-success d-flex align-items-start gap-2" role="status"><x-icon name="check"/><div>{{ session('status') }}</div></div>
@endif
@if(session('error'))
    <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger" role="alert" tabindex="-1" data-validation-summary>
        <strong>Please check the highlighted fields.</strong>
        <ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
