<x-mail::message>
# Cześć {{ $firstName }}!

Niestety musimy odwołać zajęcia, na które jesteś zapisana:

**{{ $typeName }} — {{ $when }}**
@if ($reason)

Powód: {{ $reason }}
@endif

Nic nie tracisz — wybierz w zamian inne zajęcia z wolnym miejscem.

<x-mail::button :url="$url">
Wybierz inne zajęcia
</x-mail::button>

Przepraszamy i do zobaczenia!<br>
Studio eMCeFit

<x-mail::subcopy>
Nie chcesz takich maili? [Zmień ustawienia powiadomień]({{ route('client.mail-preferences.edit') }}).
</x-mail::subcopy>
</x-mail::message>
