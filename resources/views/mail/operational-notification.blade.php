<x-mail::message>
# {{ $notification->title }}

{{ $notification->message }}

@if($notification->url)
<x-mail::button :url="url($notification->url)">
Open CareDesk
</x-mail::button>
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
