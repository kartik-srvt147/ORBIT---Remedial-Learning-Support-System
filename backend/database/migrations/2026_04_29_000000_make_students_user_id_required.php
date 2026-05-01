<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->backfillStudentUsers();

        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildStudentsTable(userIdNullable: false);

            return;
        }

        Schema::table('students', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildStudentsTable(userIdNullable: true);

            return;
        }

        Schema::table('students', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->change();
        });
    }

    private function backfillStudentUsers(): void
    {
        DB::table('students')
            ->whereNull('user_id')
            ->orderBy('id')
            ->get()
            ->each(function ($student): void {
                $email = "student{$student->id}.linked@school.test";

                if (DB::table('users')->where('email', $email)->exists()) {
                    $email = "student{$student->id}.linked.".uniqid()."@school.test";
                }

                $userId = DB::table('users')->insertGetId([
                    'name' => $student->name,
                    'email' => $email,
                    'password' => Hash::make('password'),
                    'role' => 'student',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('students')
                    ->where('id', $student->id)
                    ->update([
                        'user_id' => $userId,
                        'updated_at' => now(),
                    ]);
            });
    }

    private function rebuildStudentsTable(bool $userIdNullable): void
    {
        $nullable = $userIdNullable ? '' : 'NOT NULL';

        DB::statement('PRAGMA foreign_keys = OFF');

        DB::statement("
            CREATE TABLE students_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                teacher_id INTEGER NOT NULL,
                user_id INTEGER {$nullable},
                name VARCHAR(100) NOT NULL,
                class VARCHAR(20) NOT NULL,
                section VARCHAR(10) NOT NULL,
                roll_number INTEGER UNSIGNED NOT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
            )
        ");

        DB::statement("
            INSERT INTO students_new (
                id,
                teacher_id,
                user_id,
                name,
                class,
                section,
                roll_number,
                created_at,
                updated_at
            )
            SELECT
                id,
                teacher_id,
                user_id,
                name,
                class,
                section,
                roll_number,
                created_at,
                updated_at
            FROM students
        ");

        DB::statement('DROP TABLE students');
        DB::statement('ALTER TABLE students_new RENAME TO students');
        DB::statement('CREATE UNIQUE INDEX students_class_section_roll_number_unique ON students (class, section, roll_number)');
        DB::statement('CREATE INDEX students_teacher_id_index ON students (teacher_id)');
        DB::statement('CREATE UNIQUE INDEX students_user_id_unique ON students (user_id)');

        DB::statement('PRAGMA foreign_keys = ON');
    }
};
