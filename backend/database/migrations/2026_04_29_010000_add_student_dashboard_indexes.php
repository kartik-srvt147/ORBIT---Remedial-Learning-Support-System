<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->index('name');
            $table->index('roll_number');
            $table->index(['class', 'section']);
        });

        Schema::table('assessments', function (Blueprint $table): void {
            $table->index(['student_id', 'exam_date']);
            $table->index(['subject_id', 'exam_date']);
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table): void {
            $table->dropIndex(['student_id', 'exam_date']);
            $table->dropIndex(['subject_id', 'exam_date']);
        });

        Schema::table('students', function (Blueprint $table): void {
            $table->dropIndex(['class', 'section']);
            $table->dropIndex(['roll_number']);
            $table->dropIndex(['name']);
        });
    }
};
