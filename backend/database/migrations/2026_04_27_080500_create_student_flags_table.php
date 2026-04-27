<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->unique()->constrained('students')->cascadeOnDelete()->cascadeOnUpdate();
            $table->boolean('is_slow_learner')->default(false);
            $table->timestamp('last_evaluated_at')->nullable();
            $table->timestamps();

            $table->index('is_slow_learner');
            $table->index('last_evaluated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_flags');
    }
};
