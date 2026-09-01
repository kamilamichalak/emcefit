<?php

namespace App\Http\Requests\Client;

use App\Domain\Reservations\Models\Reservation;
use Illuminate\Foundation\Http\FormRequest;

class CancelReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $reservation = $this->route('reservation');

        return $reservation instanceof Reservation
            && ($this->user()?->hasRole('client') ?? false)
            && $reservation->client_id === $this->user()->client?->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'acknowledge_late' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Czy klient potwierdził odwołanie mimo braku prawa do odrobienia (< 1h do startu).
     */
    public function acknowledgesLate(): bool
    {
        return $this->boolean('acknowledge_late');
    }
}
