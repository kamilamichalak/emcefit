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

    protected $fillable = [
        'name',
        'description',
        'required_equipment',
        'color',
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
