<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');

            DB::statement("
                CREATE TABLE users_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    email VARCHAR(191) NOT NULL,
                    password VARCHAR NOT NULL,
                    role VARCHAR CHECK (role in ('admin','teacher','student')) NOT NULL DEFAULT 'student',
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL
                )
            ");

            DB::statement("
                INSERT INTO users_new (id, name, email, password, role, created_at, updated_at)
                SELECT id, name, email, password, role, created_at, updated_at FROM users
            ");

            DB::statement('DROP TABLE users');
            DB::statement('ALTER TABLE users_new RENAME TO users');
            DB::statement('CREATE UNIQUE INDEX users_email_unique ON users (email)');

            DB::statement('PRAGMA foreign_keys = ON');

            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->enum('role', ['admin', 'teacher', 'student'])->default('student')->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');

            DB::statement("
                CREATE TABLE users_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    email VARCHAR(191) NOT NULL,
                    password VARCHAR NOT NULL,
                    role VARCHAR CHECK (role in ('admin','teacher')) NOT NULL DEFAULT 'teacher',
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL
                )
            ");

            DB::statement("
                INSERT INTO users_new (id, name, email, password, role, created_at, updated_at)
                SELECT id, name, email,
                       password,
                       CASE WHEN role = 'student' THEN 'teacher' ELSE role END,
                       created_at, updated_at
                FROM users
            ");

            DB::statement('DROP TABLE users');
            DB::statement('ALTER TABLE users_new RENAME TO users');
            DB::statement('CREATE UNIQUE INDEX users_email_unique ON users (email)');

            DB::statement('PRAGMA foreign_keys = ON');

            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->enum('role', ['admin', 'teacher'])->default('teacher')->change();
        });
    }
};

