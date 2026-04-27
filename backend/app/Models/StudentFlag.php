<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentFlag extends Model
{
    protected $fillable = [
        'student_id',
        'is_slow_learner',
        'last_evaluated_at',
    ];

    protected $casts = [
        'is_slow_learner' => 'boolean',
        'last_evaluated_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
