<?php

namespace App\Domain\Reservations\Models;

use Carbon\CarbonInterface;
use Database\Factories\EnrollmentWindowFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EnrollmentWindow extends Model
{
    /** @use HasFactory<EnrollmentWindowFactory> */
    use HasFactory;

    protected $fillable = [
        'year',
        'month',
        'open',
        'opened_at',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'open' => 'boolean',
            'opened_at' => 'datetime',
        ];
    }

    protected static function newFactory(): EnrollmentWindowFactory
    {
        return EnrollmentWindowFactory::new();
    }

    /**
     * Czy zapisy klientów na miesiąc wskazany datą są otwarte (domyślnie: nie).
     */
    public static function isOpenFor(CarbonInterface $month): bool
    {
        return (bool) static::query()
            ->where('year', $month->year)
            ->where('month', $month->month)
            ->value('open');
    }
}
