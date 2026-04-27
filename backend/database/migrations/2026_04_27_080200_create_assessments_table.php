<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete()->cascadeOnUpdate();
            $table->decimal('marks_obtained', 5, 2);
            $table->decimal('max_marks', 5, 2);
            $table->date('exam_date');
            $table->timestamps();

            $table->index('student_id');
            $table->index('subject_id');
            $table->index('exam_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
