<x-mail::message>
# Cześć {{ $firstName }}!

Dziękujemy — Twoja wpłata za **{{ $typeName }}** ({{ $monthLabel }}) jest zaksięgowana. Twoje miejsca są potwierdzone.

@if (count($waitlisted))
<x-mail::panel>
Na te zajęcia zabrakło już miejsc, więc jesteś na **liście oczekujących** — gdy ktoś zwolni miejsce, wskoczysz automatycznie i dostaniesz maila:
@foreach ($waitlisted as $item)
- {{ $item }}
@endforeach
</x-mail::panel>
@endif

<x-mail::button :url="$url">
Moje zajęcia
</x-mail::button>

Do zobaczenia na zajęciach!<br>
Studio eMCeFit

<x-mail::subcopy>
Nie chcesz takich maili? [Zmień ustawienia powiadomień]({{ route('client.mail-preferences.edit') }}).
</x-mail::subcopy>
</x-mail::message>
