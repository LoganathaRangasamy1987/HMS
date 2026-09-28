@extends('layouts.app')
@section('title', $branch->exists ? 'Edit branch' : 'Add branch')
@section('subtitle', $branch->exists ? 'Update this branch’s contact details and availability.' : 'Create a new location within your hospital organization.')
@section('actions')
    <a class="text-link" href="{{ route('branches.index') }}">All branches <x-icon name="arrow" size="16"/></a>
@endsection
@section('content')
<form method="POST" action="{{ $branch->exists ? route('branches.update', $branch) : route('branches.store') }}">
    @csrf
    @if($branch->exists) @method('PUT') @endif
    <div class="row g-4">
        <div class="col-xl-8">
            <section class="panel">
                <div class="panel-heading"><div><h2>Branch details</h2><p>Fields marked <span class="required-mark">*</span> are required</p></div><span class="section-icon"><x-icon name="branch"/></span></div>
                <div class="panel-body"><div class="row g-4">
                    <div class="col-md-8"><x-form.input name="name" label="Branch name" :value="$branch->name" required maxlength="150" placeholder="e.g. Coimbatore Central"/></div>
                    <div class="col-md-4"><x-form.input name="code" label="Branch code" :value="$branch->code" required maxlength="20" placeholder="e.g. CBE"/></div>
                    <div class="col-md-6"><x-form.input name="phone" label="Phone number" type="tel" :value="$branch->phone" maxlength="30" autocomplete="tel"/></div>
                    <div class="col-md-6"><x-form.input name="email" label="Contact email" type="email" :value="$branch->email" maxlength="150" autocomplete="email"/></div>
                    <div class="col-12"><x-form.textarea name="address" label="Street address" :value="$branch->address" maxlength="1000" autocomplete="street-address"/></div>
                    <div class="col-md-6"><x-form.input name="city" label="City" :value="$branch->city" maxlength="100" autocomplete="address-level2"/></div>
                    <div class="col-md-6"><x-form.input name="timezone" label="Timezone" :value="$branch->timezone ?? 'Asia/Kolkata'" required maxlength="64" placeholder="Asia/Kolkata"/></div>
                    <div class="col-md-6"><x-form.select name="status" label="Status" :value="$branch->status ?? 'active'" :options="['active' => 'Active', 'inactive' => 'Inactive']" required/></div>
                </div></div>
            </section>
            <div class="form-actions"><a href="{{ route('branches.index') }}" class="btn btn-outline-secondary">Cancel</a><button class="btn btn-primary" type="submit"><x-icon name="check" size="18"/>{{ $branch->exists ? 'Save changes' : 'Create branch' }}</button></div>
        </div>
        <div class="col-xl-4"><aside class="help-card"><span class="help-card-icon"><x-icon name="pin" size="24"/></span><h3>A home for your team</h3><p>Each branch has its own departments and staff assignments. Staff can be assigned to more than one branch.</p><p>Doctor schedules and appointment dates use this branch timezone.</p><p class="mb-0">Inactive branches are unavailable for staff to enter. You cannot deactivate your current branch.</p></aside></div>
    </div>
</form>
@endsection
