<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('students')
            ->select('id', 'class', 'section')
            ->orderBy('id')
            ->get()
            ->each(function ($student): void {
                $existingSection = DB::table('sections')
                    ->where('class_name', $student->class)
                    ->where('section_name', $student->section)
                    ->first();

                $sectionId = $existingSection?->id ?? DB::table('sections')->insertGetId([
                    'class_name' => $student->class,
                    'section_name' => $student->section,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if (! DB::table('student_sections')
                    ->where('student_id', $student->id)
                    ->where('section_id', $sectionId)
                    ->exists()) {
                    DB::table('student_sections')->insert([
                        'student_id' => $student->id,
                        'section_id' => $sectionId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        //
    }
};
