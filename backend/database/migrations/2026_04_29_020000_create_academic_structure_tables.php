<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->string('class_name', 20);
            $table->string('section_name', 10);
            $table->timestamps();

            $table->unique(['class_name', 'section_name']);
            $table->index('class_name');
        });

        Schema::create('teacher_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('section_id')->constrained('sections')->cascadeOnDelete()->cascadeOnUpdate();
            $table->timestamps();

            $table->unique(['teacher_id', 'subject_id', 'section_id']);
            $table->index(['subject_id', 'section_id']);
        });

        Schema::create('student_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('section_id')->constrained('sections')->cascadeOnDelete()->cascadeOnUpdate();
            $table->timestamps();

            $table->unique(['student_id', 'section_id']);
            $table->index('section_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_sections');
        Schema::dropIfExists('teacher_subjects');
        Schema::dropIfExists('sections');
    }
};
