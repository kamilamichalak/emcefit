<x-mail::message>
# Cześć {{ \Illuminate\Support\Str::before($name, ' ') }}!

Zapraszamy do nowego systemu zapisów na zajęcia w eMCeFit Studio. Znajdziesz w nim swój karnet, swoje zajęcia i zapisy na kolejny miesiąc.

Kliknij przycisk, ustaw hasło i zaakceptuj regulamin — to zajmie minutę.

<x-mail::button :url="$link">
Aktywuj konto
</x-mail::button>

Link jest ważny 7 dni i działa jednorazowo. Jeśli wygaśnie, napisz do nas, wyślemy nowy.

Do zobaczenia na zajęciach!<br>
eMCeFit Studio
</x-mail::message>
