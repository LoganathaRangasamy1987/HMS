@extends('layouts.app')
@section('title', 'Activity log')
@section('subtitle', 'Review administrative and patient demographic changes within your hospital.')
@section('content')
<section class="panel">
    <div class="panel-heading"><div><h2>Organization activity <span class="count-badge">{{ $logs->total() }}</span></h2><p>A read-only history of saved changes</p></div><span class="section-icon"><x-icon name="activity"/></span></div>
    <form method="GET" action="{{ route('audit.index') }}" class="filter-bar">
        <div class="search-field"><x-icon name="search" size="18"/><label class="visually-hidden" for="activity-search">Search activity</label><input type="search" id="activity-search" name="q" value="{{ request('q') }}" placeholder="Search action or module" class="form-control"></div>
        <button type="submit" class="btn btn-outline-secondary">Search</button>
        @if(request()->filled('q'))<a href="{{ route('audit.index') }}" class="filter-reset">Clear</a>@endif
    </form>
    <div class="table-responsive"><table class="table app-table mb-0">
        <thead><tr><th>Date & time</th><th>Team member</th><th>Action</th><th>Module</th><th>Record</th><th>Details</th></tr></thead>
        <tbody>@forelse($logs as $log)
            <tr>
                <td><time datetime="{{ $log->created_at->toIso8601String() }}"><strong class="d-block fw-medium">{{ $log->created_at->format('d M Y') }}</strong><small class="text-secondary">{{ $log->created_at->format('h:i:s A') }}</small></time></td>
                <td><div class="person-cell"><span class="avatar avatar-sm">{{ mb_strtoupper(mb_substr($log->user?->name ?? 'System', 0, 1)) }}</span><div><strong>{{ $log->user?->name ?? 'System' }}</strong>@if($log->branch)<small>{{ $log->branch->name }}</small>@endif</div></div></td>
                <td>{{ ucfirst(str_replace(['_', '.'], ' ', $log->action)) }}</td>
                <td><span class="module-label">{{ ucfirst(str_replace('_', ' ', $log->module)) }}</span></td>
                <td class="text-secondary">{{ $log->record_id ? '#'.$log->record_id : '—' }}</td>
                <td>
                    @if($log->module === 'patients' && $log->new_values)
                        @php
                            $labels = ['uhid' => 'UHID', 'first_name' => 'First name', 'last_name' => 'Last name', 'date_of_birth' => 'Date of birth', 'date_of_birth_unknown' => 'DOB unknown', 'gender' => 'Gender', 'mobile' => 'Mobile', 'email' => 'Email', 'blood_group' => 'Blood group', 'address' => 'Address', 'city' => 'City', 'state' => 'State', 'pincode' => 'Postal code', 'emergency_contact_name' => 'Emergency contact', 'emergency_contact_mobile' => 'Emergency mobile', 'status' => 'Status'];
                            $changes = collect($log->new_values)->filter(fn ($value, $key) => ($log->old_values[$key] ?? null) !== $value);
                        @endphp
                        <details><summary class="text-link">View {{ $changes->count() }} changed {{ Str::plural('field', $changes->count()) }}</summary>
                            <dl class="small mt-2 mb-0">@foreach($changes as $key => $newValue)
                                <div class="mb-2"><dt>{{ $labels[$key] ?? Str::headline($key) }}</dt><dd class="mb-0 text-break">@if($log->action === 'updated')<span class="text-secondary">{{ filled($log->old_values[$key] ?? null) ? (is_bool($log->old_values[$key]) ? ($log->old_values[$key] ? 'Yes' : 'No') : $log->old_values[$key]) : 'Not recorded' }} → </span>@endif{{ filled($newValue) ? (is_bool($newValue) ? ($newValue ? 'Yes' : 'No') : $newValue) : 'Not recorded' }}</dd></div>
                            @endforeach</dl>
                        </details>
                    @else
                        <span class="text-secondary">—</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6"><div class="empty-state"><span class="empty-icon"><x-icon name="activity" size="30"/></span><h3>{{ request()->filled('q') ? 'No matching activity' : 'Your activity log starts here' }}</h3><p>{{ request()->filled('q') ? 'Try another action or module name.' : 'Saved organization and patient changes appear here.' }}</p></div></td></tr>
        @endforelse</tbody>
    </table></div>
    @if($logs->hasPages())<div class="panel-pagination">{{ $logs->withQueryString()->links() }}</div>@endif
</section>
<div class="subtle-note mt-3"><x-icon name="shield" size="16"/><span>This log is read-only. Times are shown in {{ config('app.timezone') }}.</span></div>
@endsection
