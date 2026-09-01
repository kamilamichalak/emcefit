<?php

namespace App\Domain\Trainers\Models;

use App\Domain\Scheduling\Models\ClassGroup;
use App\Models\User;
use Database\Factories\TrainerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trainer extends Model
{
    /** @use HasFactory<TrainerFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'specialization',
    ];

    protected static function newFactory(): TrainerFactory
    {
        return TrainerFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function classGroups(): HasMany
    {
        return $this->hasMany(ClassGroup::class);
    }
}
