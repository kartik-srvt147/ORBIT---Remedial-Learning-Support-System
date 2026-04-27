<?php

namespace Database\Seeders;

use App\Models\Assessment;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class SchoolDemoSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = collect(['Mathematics', 'Science', 'English', 'Social Studies'])
            ->map(fn (string $name) => Subject::query()->firstOrCreate(['name' => $name]));

        $teachers = collect(range(1, 5))->map(function (int $index) {
            return User::query()->create([
                'name' => "Teacher {$index}",
                'email' => "teacher{$index}@school.test",
                'password' => 'password',
                'role' => 'teacher',
            ]);
        });

        $students = collect(range(1, 20))->map(function (int $index) use ($teachers) {
            $teacher = $teachers[($index - 1) % $teachers->count()];
            $classNumber = 6 + (($index - 1) % 5);
            $section = chr(65 + (($index - 1) % 2)); // A/B

            return Student::query()->create([
                'teacher_id' => $teacher->id,
                'name' => "Student {$index}",
                'class' => (string) $classNumber,
                'section' => $section,
                'roll_number' => $index,
            ]);
        });

        foreach ($students as $studentIndex => $student) {
            $isLikelySlow = $studentIndex < 8; // first 8 students likely to be flagged

            foreach ($subjects as $subject) {
                $maxMarks = 100;
                $marks = $isLikelySlow
                    ? random_int(20, 45)
                    : random_int(50, 90);

                Assessment::query()->create([
                    'student_id' => $student->id,
                    'subject_id' => $subject->id,
                    'marks_obtained' => $marks,
                    'max_marks' => $maxMarks,
                    'exam_date' => Carbon::now()->subDays(random_int(5, 45))->toDateString(),
                ]);
            }

            for ($dayOffset = 1; $dayOffset <= 20; $dayOffset++) {
                $presentProbability = $isLikelySlow ? 55 : 88;
                $isPresent = random_int(1, 100) <= $presentProbability;

                Attendance::query()->create([
                    'student_id' => $student->id,
                    'date' => Carbon::now()->subDays($dayOffset)->toDateString(),
                    'status' => $isPresent ? 'present' : 'absent',
                ]);
            }
        }
    }
}
