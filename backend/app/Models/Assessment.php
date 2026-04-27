<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Assessment extends Model
{
    protected $fillable = [
        'student_id',
        'subject_id',
        'marks_obtained',
        'max_marks',
        'exam_date',
    ];

    protected $casts = [
        'exam_date' => 'date',
        'marks_obtained' => 'decimal:2',
        'max_marks' => 'decimal:2',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
