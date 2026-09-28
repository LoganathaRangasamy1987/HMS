@props(['value'])
<span @class(['status-badge', 'status-active' => $value === 'active', 'status-inactive' => $value !== 'active'])><span class="status-dot" aria-hidden="true"></span>{{ ucfirst($value) }}</span>
