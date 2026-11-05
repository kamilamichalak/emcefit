<?php

namespace App\Domain\Clients\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Wpis z publicznego formularza /dane — imię i nazwisko + e-mail klientki,
 * dopasowywany ręcznie do kont klientek. Nikt poza adminem/bazą go nie widzi.
 */
class EmailSubmission extends Model
{
    protected $fillable = ['full_name', 'email', 'consent_at'];

    protected function casts(): array
    {
        return ['consent_at' => 'datetime'];
    }
}
