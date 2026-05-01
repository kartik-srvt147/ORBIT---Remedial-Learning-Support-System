<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    protected $fillable = [
        'class_name',
        'section_name',
    ];

    public function studentSections(): HasMany
    {
        return $this->hasMany(StudentSection::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_sections')
            ->withTimestamps();
    }

    public function teacherSubjects(): HasMany
    {
        return $this->hasMany(TeacherSubject::class);
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'teacher_subjects', 'section_id', 'teacher_id')
            ->withPivot('subject_id')
            ->withTimestamps();
    }
}
