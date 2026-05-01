<?php

namespace Database\Seeders;

use App\Models\Assessment;
use App\Models\Attendance;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentSection;
use App\Models\Subject;
use App\Models\TeacherSubject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class SchoolDemoSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = collect(['Mathematics', 'Science', 'English', 'Social Studies'])
            ->map(fn (string $name) => Subject::query()->firstOrCreate(['name' => $name]));

        $sections = collect(range(6, 10))->flatMap(function (int $classNumber) {
            return collect(['A', 'B'])->map(fn (string $sectionName) => Section::query()->firstOrCreate([
                'class_name' => (string) $classNumber,
                'section_name' => $sectionName,
            ]));
        })->values();

        $teachers = collect(range(1, 5))->map(function (int $index) {
            return User::query()->create([
                'name' => "Teacher {$index}",
                'email' => "teacher{$index}@school.test",
                'password' => 'password',
                'role' => 'teacher',
            ]);
        });

        foreach ($teachers as $teacherIndex => $teacher) {
            foreach ($subjects as $subjectIndex => $subject) {
                $section = $sections[($teacherIndex + $subjectIndex) % $sections->count()];
                TeacherSubject::query()->firstOrCreate([
                    'teacher_id' => $teacher->id,
                    'subject_id' => $subject->id,
                    'section_id' => $section->id,
                ]);
            }
        }

        $students = collect(range(1, 20))->map(function (int $index) use ($teachers, $sections) {
            $teacher = $teachers[($index - 1) % $teachers->count()];
            $section = $sections[($index - 1) % $sections->count()];
            $studentUser = User::query()->create([
                'name' => "Student {$index}",
                'email' => "student{$index}@school.test",
                'password' => 'password',
                'role' => 'student',
            ]);

            return Student::query()->create([
                'teacher_id' => $teacher->id,
                'user_id' => $studentUser->id,
                'name' => "Student {$index}",
                'class' => $section->class_name,
                'section' => $section->section_name,
                'roll_number' => $index,
            ]);

            StudentSection::query()->firstOrCreate([
                'student_id' => $student->id,
                'section_id' => $section->id,
            ]);

            return $student;
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
