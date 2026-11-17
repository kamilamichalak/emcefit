<x-mail::message>
# Nowe zgłoszenie #{{ $helpRequest->id }}

**Od:** {{ $helpRequest->name }} ({{ $helpRequest->email }}){{ $helpRequest->user_id ? ' — zalogowana' : ' — niezalogowana' }}

**Wiadomość:**

{{ $helpRequest->message }}

@if ($helpRequest->page_url)
<small>Strona: {{ $helpRequest->page_url }}</small><br>
@endif
<small>Urządzenie: {{ $helpRequest->user_agent }}</small>
</x-mail::message>
