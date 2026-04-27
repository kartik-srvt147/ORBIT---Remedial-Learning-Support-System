<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('name', 100);
            $table->string('class', 20);
            $table->string('section', 10);
            $table->unsignedInteger('roll_number');
            $table->timestamps();

            $table->unique(['class', 'section', 'roll_number']);
            $table->index('teacher_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
