<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remedial_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete()->cascadeOnUpdate();
            $table->string('action_type', 50);
            $table->text('description');
            $table->timestamps();

            $table->index('student_id');
            $table->index('subject_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remedial_actions');
    }
};
