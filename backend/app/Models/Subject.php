<?php

namespace App\Models;

use App\Models\Assessment;
use App\Models\RemedialAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    protected $fillable = [
        'name',
    ];

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    public function remedialActions(): HasMany
    {
        return $this->hasMany(RemedialAction::class);
    }

    public function teacherSubjects(): HasMany
    {
        return $this->hasMany(TeacherSubject::class);
    }
}
