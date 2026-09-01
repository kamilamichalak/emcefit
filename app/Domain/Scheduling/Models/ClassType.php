<?php

namespace App\Domain\Scheduling\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassType extends Model
{
    protected $fillable = [
        'name',
        'description',
        'required_equipment',
    ];

    public function classGroups(): HasMany
    {
        return $this->hasMany(ClassGroup::class);
    }
}
