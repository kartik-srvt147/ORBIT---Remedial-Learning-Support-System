<?php

namespace App\Http\Controllers;

use App\Models\Section;
use App\Models\Student;
use App\Models\StudentSection;
use App\Models\Subject;
use App\Models\TeacherSubject;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function overview(): JsonResponse
    {
        $sections = Section::query()
            ->withCount('students')
            ->with(['students:id,name,class,section,roll_number'])
            ->orderBy('class_name')
            ->orderBy('section_name')
            ->get();

        $teachersPerSubject = Subject::query()
            ->with(['teacherSubjects.teacher:id,name,email', 'teacherSubjects.section:id,class_name,section_name'])
            ->withCount('teacherSubjects')
            ->orderBy('name')
            ->get()
            ->map(fn (Subject $subject): array => [
                'subject_id' => $subject->id,
                'subject_name' => $subject->name,
                'assignment_count' => $subject->teacher_subjects_count,
                'teachers' => $subject->teacherSubjects
                    ->map(fn (TeacherSubject $assignment): array => [
                        'teacher' => $assignment->teacher,
                        'section' => $assignment->section,
                    ])
                    ->values(),
            ])
            ->values();

        return response()->json([
            'sections' => $sections,
            'teachers_per_subject' => $teachersPerSubject,
        ]);
    }

    public function createTeacher(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'assignments' => ['nullable', 'array'],
            'assignments.*.subject_id' => ['required_with:assignments', 'integer', 'exists:subjects,id'],
            'assignments.*.section_id' => ['required_with:assignments', 'integer', 'exists:sections,id'],
        ]);

        $defaultPassword = $this->defaultPassword();

        $teacher = DB::transaction(function () use ($data, $defaultPassword): User {
            $teacher = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $defaultPassword,
                'role' => 'teacher',
            ]);

            foreach ($data['assignments'] ?? [] as $assignment) {
                TeacherSubject::query()->firstOrCreate([
                    'teacher_id' => $teacher->id,
                    'subject_id' => $assignment['subject_id'],
                    'section_id' => $assignment['section_id'],
                ]);
            }

            return $teacher->load(['teacherSubjects.subject', 'teacherSubjects.section']);
        });

        return response()->json([
            'message' => 'Teacher created successfully.',
            'default_password' => $defaultPassword,
            'teacher' => $teacher,
        ], 201);
    }

    public function createStudent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'teacher_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'teacher')],
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'roll_number' => ['required', 'integer', 'min:1'],
        ]);

        $section = Section::query()->findOrFail($data['section_id']);
        $defaultPassword = $this->defaultPassword();

        $student = DB::transaction(function () use ($data, $section, $defaultPassword): Student {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $defaultPassword,
                'role' => 'student',
            ]);

            $student = Student::query()->create([
                'teacher_id' => $data['teacher_id'],
                'user_id' => $user->id,
                'name' => $data['name'],
                'class' => $section->class_name,
                'section' => $section->section_name,
                'roll_number' => $data['roll_number'],
            ]);

            StudentSection::query()->firstOrCreate([
                'student_id' => $student->id,
                'section_id' => $section->id,
            ]);

            return $student->load(['user:id,name,email,role', 'teacher:id,name,email', 'sections']);
        });

        return response()->json([
            'message' => 'Student created successfully.',
            'default_password' => $defaultPassword,
            'student' => $student,
        ], 201);
    }

    public function createSubject(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:subjects,name'],
        ]);

        $subject = Subject::query()->create($data);

        return response()->json([
            'message' => 'Subject created successfully.',
            'subject' => $subject,
        ], 201);
    }

    public function createSection(Request $request): JsonResponse
    {
        $data = $request->validate([
            'class_name' => ['required', 'string', 'max:20'],
            'section_name' => ['required', 'string', 'max:10'],
        ]);

        $section = Section::query()->create($data);

        return response()->json([
            'message' => 'Section created successfully.',
            'section' => $section,
        ], 201);
    }

    public function assignTeacher(Request $request): JsonResponse
    {
        $data = $request->validate([
            'teacher_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'teacher')],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'section_id' => ['required', 'integer', 'exists:sections,id'],
        ]);

        $assignment = TeacherSubject::query()->firstOrCreate($data)
            ->load(['teacher:id,name,email', 'subject:id,name', 'section:id,class_name,section_name']);

        return response()->json([
            'message' => 'Teacher assigned successfully.',
            'assignment' => $assignment,
        ], 201);
    }

    public function assignStudent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'section_id' => ['required', 'integer', 'exists:sections,id'],
        ]);

        $section = Section::query()->findOrFail($data['section_id']);

        $assignment = DB::transaction(function () use ($data, $section): StudentSection {
            Student::query()
                ->where('id', $data['student_id'])
                ->update([
                    'class' => $section->class_name,
                    'section' => $section->section_name,
                ]);

            return StudentSection::query()->firstOrCreate($data)
                ->load(['student:id,name,class,section,roll_number', 'section:id,class_name,section_name']);
        });

        return response()->json([
            'message' => 'Student assigned successfully.',
            'assignment' => $assignment,
        ], 201);
    }

    private function defaultPassword(): string
    {
        return 'Orbit@'.Str::random(8);
    }
}
