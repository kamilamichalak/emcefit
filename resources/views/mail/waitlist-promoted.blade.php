<x-mail::message>
# Cześć {{ $firstName }}!

Dobra wiadomość — zwolniło się miejsce i **jesteś zapisana** z listy oczekujących:

**{{ $typeName }} — {{ $when }}**

Jeśli jednak nie możesz przyjść, zwolnij miejsce w zakładce „Moje zajęcia”, żeby skorzystał ktoś inny.

<x-mail::button :url="$url">
Moje zajęcia
</x-mail::button>

Do zobaczenia na zajęciach!<br>
Studio eMCeFit

<x-mail::subcopy>
Nie chcesz takich maili? [Zmień ustawienia powiadomień]({{ route('client.mail-preferences.edit') }}).
</x-mail::subcopy>
</x-mail::message>
