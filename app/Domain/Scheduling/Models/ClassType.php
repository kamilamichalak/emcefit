<?php

namespace App\Domain\Scheduling\Models;

use Database\Factories\ClassTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassType extends Model
{
    /** @use HasFactory<ClassTypeFactory> */
    use HasFactory;

    /**
     * Dozwolone ikony typu zajęć — nazwy komponentów z lucide-vue-next (spec sekcja 18).
     * Ta sama lista jest źródłem pickera na froncie (resources/js/Utils/classTypeIcons.js).
     */
    public const ICONS = [
        'Dumbbell', 'Activity', 'HeartPulse', 'Flame', 'Zap', 'Timer',
        'Music', 'Music2', 'Music4', 'Footprints', 'PersonStanding', 'Bike',
        'Waves', 'Target', 'Sparkles', 'Repeat',
    ];

    public const DEFAULT_ICON = 'Dumbbell';

    protected $fillable = [
        'name',
        'description',
        'required_equipment',
        'color',
        'icon',
        'default_capacity',
    ];

    protected function casts(): array
    {
        return [
            'default_capacity' => 'integer',
        ];
    }

    protected static function newFactory(): ClassTypeFactory
    {
        return ClassTypeFactory::new();
    }

    public function classGroups(): HasMany
    {
        return $this->hasMany(ClassGroup::class);
    }
}
