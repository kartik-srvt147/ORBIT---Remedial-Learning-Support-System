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
use App\Services\RecommendationService;
use App\Services\SlowLearnerService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SchoolDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->cleanupOldGenericDemoRows();

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@orbit.test'],
            [
                'name' => 'Aarav Mehta',
                'password' => 'password',
                'role' => 'admin',
            ]
        );

        $subjects = collect([
            'Mathematics',
            'Science',
            'English',
            'Social Studies',
            'Computer Science',
            'Hindi',
        ])->mapWithKeys(fn (string $name) => [
            $name => Subject::query()->updateOrCreate(['name' => $name], ['name' => $name]),
        ]);

        $sections = collect([
            ['class_name' => '9', 'section_name' => 'A'],
            ['class_name' => '9', 'section_name' => 'B'],
            ['class_name' => '10', 'section_name' => 'A'],
            ['class_name' => '10', 'section_name' => 'B'],
            ['class_name' => '11', 'section_name' => 'A'],
            ['class_name' => '11', 'section_name' => 'B'],
        ])->mapWithKeys(fn (array $row) => [
            "{$row['class_name']}-{$row['section_name']}" => Section::query()->updateOrCreate(
                ['class_name' => $row['class_name'], 'section_name' => $row['section_name']],
                $row
            ),
        ]);

        $teachers = collect([
            ['name' => 'Ananya Rao', 'email' => 'teacher1@school.test'],
            ['name' => 'Rohan Kapoor', 'email' => 'teacher2@school.test'],
            ['name' => 'Priya Nair', 'email' => 'teacher3@school.test'],
            ['name' => 'Vikram Singh', 'email' => 'teacher4@school.test'],
            ['name' => 'Meera Iyer', 'email' => 'teacher5@school.test'],
            ['name' => 'Sanjay Menon', 'email' => 'teacher6@school.test'],
        ])->map(fn (array $row) => User::query()->updateOrCreate(
            ['email' => $row['email']],
            [
                'name' => $row['name'],
                'password' => 'password',
                'role' => 'teacher',
            ]
        ))->values();

        $teacherAssignments = [
            ['teacher' => 0, 'subject' => 'Mathematics', 'sections' => ['9-A', '10-A']],
            ['teacher' => 1, 'subject' => 'Science', 'sections' => ['9-B', '10-B']],
            ['teacher' => 2, 'subject' => 'English', 'sections' => ['9-A', '9-B', '11-A']],
            ['teacher' => 3, 'subject' => 'Social Studies', 'sections' => ['10-A', '10-B']],
            ['teacher' => 4, 'subject' => 'Computer Science', 'sections' => ['11-A', '11-B']],
            ['teacher' => 5, 'subject' => 'Hindi', 'sections' => ['9-A', '10-A', '11-B']],
            ['teacher' => 0, 'subject' => 'Mathematics', 'sections' => ['11-A', '11-B']],
            ['teacher' => 1, 'subject' => 'Science', 'sections' => ['11-A', '11-B']],
        ];

        foreach ($teacherAssignments as $assignment) {
            foreach ($assignment['sections'] as $sectionKey) {
                TeacherSubject::query()->firstOrCreate([
                    'teacher_id' => $teachers[$assignment['teacher']]->id,
                    'subject_id' => $subjects[$assignment['subject']]->id,
                    'section_id' => $sections[$sectionKey]->id,
                ]);
            }
        }

        $students = collect([
            ['name' => 'Aditya Sharma', 'email' => 'student1@school.test', 'section' => '9-A', 'roll' => 1, 'teacher' => 0, 'profile' => 'strong'],
            ['name' => 'Ishita Verma', 'email' => 'student2@school.test', 'section' => '9-A', 'roll' => 2, 'teacher' => 2, 'profile' => 'average'],
            ['name' => 'Kabir Malhotra', 'email' => 'student3@school.test', 'section' => '9-A', 'roll' => 3, 'teacher' => 5, 'profile' => 'slow'],
            ['name' => 'Nisha Thomas', 'email' => 'student4@school.test', 'section' => '9-B', 'roll' => 1, 'teacher' => 1, 'profile' => 'attendance'],
            ['name' => 'Arjun Reddy', 'email' => 'student5@school.test', 'section' => '9-B', 'roll' => 2, 'teacher' => 2, 'profile' => 'strong'],
            ['name' => 'Sara Khan', 'email' => 'student6@school.test', 'section' => '9-B', 'roll' => 3, 'teacher' => 1, 'profile' => 'average'],
            ['name' => 'Dev Patel', 'email' => 'student7@school.test', 'section' => '10-A', 'roll' => 1, 'teacher' => 0, 'profile' => 'slow'],
            ['name' => 'Maya Krishnan', 'email' => 'student8@school.test', 'section' => '10-A', 'roll' => 2, 'teacher' => 3, 'profile' => 'strong'],
            ['name' => 'Riya Bose', 'email' => 'student9@school.test', 'section' => '10-A', 'roll' => 3, 'teacher' => 5, 'profile' => 'average'],
            ['name' => 'Karan Gill', 'email' => 'student10@school.test', 'section' => '10-B', 'roll' => 1, 'teacher' => 1, 'profile' => 'attendance'],
            ['name' => 'Tara Joshi', 'email' => 'student11@school.test', 'section' => '10-B', 'roll' => 2, 'teacher' => 3, 'profile' => 'strong'],
            ['name' => 'Neel Banerjee', 'email' => 'student12@school.test', 'section' => '10-B', 'roll' => 3, 'teacher' => 1, 'profile' => 'slow'],
            ['name' => 'Zoya Ahmed', 'email' => 'student13@school.test', 'section' => '11-A', 'roll' => 1, 'teacher' => 4, 'profile' => 'average'],
            ['name' => 'Vivaan Choudhary', 'email' => 'student14@school.test', 'section' => '11-A', 'roll' => 2, 'teacher' => 0, 'profile' => 'strong'],
            ['name' => 'Aisha Fernandes', 'email' => 'student15@school.test', 'section' => '11-A', 'roll' => 3, 'teacher' => 2, 'profile' => 'attendance'],
            ['name' => 'Om Prakash', 'email' => 'student16@school.test', 'section' => '11-B', 'roll' => 1, 'teacher' => 4, 'profile' => 'slow'],
            ['name' => 'Diya Sethi', 'email' => 'student17@school.test', 'section' => '11-B', 'roll' => 2, 'teacher' => 5, 'profile' => 'strong'],
            ['name' => 'Manav Kulkarni', 'email' => 'student18@school.test', 'section' => '11-B', 'roll' => 3, 'teacher' => 1, 'profile' => 'average'],
        ])->map(function (array $row) use ($teachers, $sections): array {
            $section = $sections[$row['section']];
            $studentUser = User::query()->updateOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $row['name'],
                    'password' => 'password',
                    'role' => 'student',
                ]
            );

            $student = Student::query()->updateOrCreate(
                [
                    'class' => $section->class_name,
                    'section' => $section->section_name,
                    'roll_number' => $row['roll'],
                ],
                [
                    'teacher_id' => $teachers[$row['teacher']]->id,
                    'user_id' => $studentUser->id,
                    'name' => $row['name'],
                ]
            );

            StudentSection::query()->updateOrCreate(
                ['student_id' => $student->id],
                ['section_id' => $section->id]
            );

            return [
                'student' => $student,
                'profile' => $row['profile'],
            ];
        });

        foreach ($students as $demoStudent) {
            $this->seedAssessments($demoStudent['student'], $subjects, $demoStudent['profile']);
            $this->seedAttendance($demoStudent['student'], $demoStudent['profile']);
        }

        app(SlowLearnerService::class)->evaluateAllStudents();
        app(RecommendationService::class)->generateForAllStudents();
    }

    private function cleanupOldGenericDemoRows(): void
    {
        Student::query()
            ->where('name', 'like', 'Student %')
            ->delete();

        User::query()
            ->where(function ($query): void {
                $query->where('name', 'like', 'Student %')
                    ->orWhere('email', 'like', 'student%.linked%@school.test')
                    ->orWhere('email', 'like', 'student%.linked@school.test');
            })
            ->delete();

        $oldSectionIds = Section::query()
            ->whereNotIn('class_name', ['9', '10', '11'])
            ->pluck('id');

        TeacherSubject::query()
            ->whereIn('section_id', $oldSectionIds)
            ->delete();

        $usedSectionIds = DB::table('student_sections')
            ->pluck('section_id')
            ->merge(DB::table('teacher_subjects')->pluck('section_id'))
            ->unique()
            ->values()
            ->all();

        Section::query()
            ->whereNotIn('id', $usedSectionIds)
            ->whereNotIn('class_name', ['9', '10', '11'])
            ->delete();
    }

    private function seedAssessments(Student $student, $subjects, string $profile): void
    {
        Assessment::query()
            ->where('student_id', $student->id)
            ->delete();

        $baseByProfile = [
            'strong' => [72, 95],
            'average' => [50, 72],
            'slow' => [22, 42],
            'attendance' => [58, 82],
        ];

        [$min, $max] = $baseByProfile[$profile] ?? [50, 75];
        $examDates = [
            Carbon::now()->subMonths(4)->toDateString(),
            Carbon::now()->subMonths(3)->toDateString(),
            Carbon::now()->subMonths(2)->toDateString(),
            Carbon::now()->subMonth()->toDateString(),
        ];

        foreach ($subjects->values() as $subjectIndex => $subject) {
            foreach ($examDates as $dateIndex => $examDate) {
                $trendBoost = $dateIndex * 3;
                $subjectAdjustment = ($subjectIndex % 3) * -4;
                $marks = min(max(random_int($min, $max) + $trendBoost + $subjectAdjustment, 18), 98);

                Assessment::query()->create(
                    [
                        'student_id' => $student->id,
                        'subject_id' => $subject->id,
                        'exam_date' => $examDate,
                        'marks_obtained' => $marks,
                        'max_marks' => 100,
                    ]
                );
            }
        }
    }

    private function seedAttendance(Student $student, string $profile): void
    {
        Attendance::query()
            ->where('student_id', $student->id)
            ->delete();

        $presentEvery = $profile === 'attendance' ? 3 : ($profile === 'slow' ? 4 : 9);

        for ($dayOffset = 1; $dayOffset <= 30; $dayOffset++) {
            Attendance::query()->create(
                [
                    'student_id' => $student->id,
                    'date' => Carbon::now()->subDays($dayOffset)->toDateString(),
                    'status' => $dayOffset % $presentEvery === 0 ? 'absent' : 'present',
                ]
            );
        }
    }
}
