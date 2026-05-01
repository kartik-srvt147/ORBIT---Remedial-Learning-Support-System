<?php

namespace App\Models;

use App\Models\Assessment;
use App\Models\Attendance;
use App\Models\RemedialAction;
use App\Models\StudentFlag;
use App\Models\TeacherNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Student extends Model
{
    protected $fillable = [
        'teacher_id',
        'user_id',
        'name',
        'class',
        'section',
        'roll_number',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(Attendance::class, 'student_id');
    }

    public function remedialActions(): HasMany
    {
        return $this->hasMany(RemedialAction::class);
    }

    public function flag(): HasOne
    {
        return $this->hasOne(StudentFlag::class);
    }

    public function teacherNotifications(): HasMany
    {
        return $this->hasMany(TeacherNotification::class);
    }

    public function studentSections(): HasMany
    {
        return $this->hasMany(StudentSection::class);
    }

    public function sections()
    {
        return $this->belongsToMany(Section::class, 'student_sections')
            ->withTimestamps();
    }
}
