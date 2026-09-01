<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dane klubu
    |--------------------------------------------------------------------------
    | Wykorzystywane m.in. na stronie potwierdzenia zgłoszenia (dane do przelewu —
    | regulamin sekcja 5, pkt 29-30). Edycja przez admina w panelu to zadanie na Fazę 3.
    */

    'name' => env('CLUB_NAME', 'Studio Fitness eMCeFit'),

    'bank_account' => env('CLUB_BANK_ACCOUNT', '25 1140 2004 0000 3202 8400 1750'),

];
