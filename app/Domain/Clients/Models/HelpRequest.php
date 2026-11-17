<?php

namespace App\Domain\Clients\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Zgłoszenie z formularza „Pomoc” (problem z systemem albo uwaga klientki). */
class HelpRequest extends Model
{
    protected $fillable = ['user_id', 'name', 'email', 'message', 'page_url', 'user_agent', 'handled_at'];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
