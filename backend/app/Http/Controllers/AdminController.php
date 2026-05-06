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
    public function teachers(Request $request): JsonResponse
    {
        $query = User::query()
            ->where('role', 'teacher')
            ->with(['teacherSubjects.subject:id,name', 'teacherSubjects.section:id,class_name,section_name'])
            ->orderBy('name');

        $this->applyUserSearch($query, $request);

        return $this->paginated($query->paginate($this->limit($request)), 'teachers');
    }

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
            'counts' => [
                'teachers' => User::query()->where('role', 'teacher')->count(),
                'students' => Student::query()->count(),
                'subjects' => Subject::query()->count(),
                'sections' => Section::query()->count(),
            ],
            'sections' => $sections,
            'teachers_per_subject' => $teachersPerSubject,
        ]);
    }

    public function createTeacher(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'password' => ['nullable', 'string', 'min:8'],
            'assignments' => ['nullable', 'array'],
            'assignments.*.subject_id' => ['required_with:assignments', 'integer', 'exists:subjects,id'],
            'assignments.*.section_id' => ['required_with:assignments', 'integer', 'exists:sections,id'],
        ]);

        $defaultPassword = $data['password'] ?? $this->defaultPassword();

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

    public function updateTeacher(Request $request, User $teacher): JsonResponse
    {
        abort_if($teacher->role !== 'teacher', 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($teacher->id)],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $teacher->fill([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        if (! empty($data['password'])) {
            $teacher->password = $data['password'];
        }

        $teacher->save();

        return response()->json([
            'message' => 'Teacher updated successfully.',
            'teacher' => $teacher->load(['teacherSubjects.subject', 'teacherSubjects.section']),
        ]);
    }

    public function deleteTeacher(User $teacher): JsonResponse
    {
        abort_if($teacher->role !== 'teacher', 404);

        if ($teacher->students()->exists()) {
            return response()->json([
                'message' => 'Cannot delete teacher while students are assigned.',
            ], 422);
        }

        $teacher->delete();

        return response()->json(['message' => 'Teacher deleted successfully.']);
    }

    public function students(Request $request): JsonResponse
    {
        $query = Student::query()
            ->with(['user:id,name,email,role', 'teacher:id,name,email', 'sections:id,class_name,section_name'])
            ->orderBy('name');

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('roll_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('class')) {
            $query->where('class', (string) $request->query('class'));
        }

        if ($request->filled('section')) {
            $query->where('section', (string) $request->query('section'));
        }

        return $this->paginated($query->paginate($this->limit($request)), 'students');
    }

    public function createStudent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'password' => ['nullable', 'string', 'min:8'],
            'teacher_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'teacher')],
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'roll_number' => ['required', 'integer', 'min:1'],
        ]);

        $section = Section::query()->findOrFail($data['section_id']);
        $defaultPassword = $data['password'] ?? $this->defaultPassword();

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

    public function updateStudent(Request $request, Student $student): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($student->user_id)],
            'password' => ['nullable', 'string', 'min:8'],
            'teacher_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'teacher')],
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'roll_number' => ['required', 'integer', 'min:1'],
        ]);

        $section = Section::query()->findOrFail($data['section_id']);

        DB::transaction(function () use ($data, $student, $section): void {
            $student->update([
                'teacher_id' => $data['teacher_id'],
                'name' => $data['name'],
                'class' => $section->class_name,
                'section' => $section->section_name,
                'roll_number' => $data['roll_number'],
            ]);

            $student->user?->fill([
                'name' => $data['name'],
                'email' => $data['email'],
            ]);

            if (! empty($data['password'])) {
                $student->user->password = $data['password'];
            }

            $student->user?->save();

            StudentSection::query()->updateOrCreate(
                ['student_id' => $student->id],
                ['section_id' => $section->id]
            );
        });

        return response()->json([
            'message' => 'Student updated successfully.',
            'student' => $student->fresh()->load(['user:id,name,email,role', 'teacher:id,name,email', 'sections']),
        ]);
    }

    public function deleteStudent(Student $student): JsonResponse
    {
        $user = $student->user;
        $student->delete();
        $user?->delete();

        return response()->json(['message' => 'Student deleted successfully.']);
    }

    public function subjects(Request $request): JsonResponse
    {
        $query = Subject::query()
            ->withCount('teacherSubjects')
            ->orderBy('name');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.trim((string) $request->query('search')).'%');
        }

        return $this->paginated($query->paginate($this->limit($request)), 'subjects');
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

    public function updateSubject(Request $request, Subject $subject): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('subjects', 'name')->ignore($subject->id)],
        ]);

        $subject->update($data);

        return response()->json([
            'message' => 'Subject updated successfully.',
            'subject' => $subject,
        ]);
    }

    public function deleteSubject(Subject $subject): JsonResponse
    {
        $subject->delete();

        return response()->json(['message' => 'Subject deleted successfully.']);
    }

    public function sections(Request $request): JsonResponse
    {
        $query = Section::query()
            ->withCount('students')
            ->with(['teacherSubjects.teacher:id,name,email', 'teacherSubjects.subject:id,name'])
            ->orderBy('class_name')
            ->orderBy('section_name');

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($query) use ($search): void {
                $query->where('class_name', 'like', "%{$search}%")
                    ->orWhere('section_name', 'like', "%{$search}%");
            });
        }

        return $this->paginated($query->paginate($this->limit($request)), 'sections');
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

    public function showSection(Section $section): JsonResponse
    {
        $section->load([
            'students:id,name,class,section,roll_number',
            'teacherSubjects.teacher:id,name,email',
            'teacherSubjects.subject:id,name',
        ]);

        return response()->json([
            'section' => $section,
        ]);
    }

    public function updateSection(Request $request, Section $section): JsonResponse
    {
        $data = $request->validate([
            'class_name' => ['required', 'string', 'max:20'],
            'section_name' => ['required', 'string', 'max:10'],
        ]);

        $section->update($data);

        Student::query()
            ->whereHas('sections', fn ($query) => $query->where('sections.id', $section->id))
            ->update([
                'class' => $section->class_name,
                'section' => $section->section_name,
            ]);

        return response()->json([
            'message' => 'Section updated successfully.',
            'section' => $section,
        ]);
    }

    public function deleteSection(Section $section): JsonResponse
    {
        $section->delete();

        return response()->json(['message' => 'Section deleted successfully.']);
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

    private function applyUserSearch($query, Request $request): void
    {
        if (! $request->filled('search')) {
            return;
        }

        $search = trim((string) $request->query('search'));

        $query->where(function ($query) use ($search): void {
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        });
    }

    private function paginated($paginator, string $key): JsonResponse
    {
        return response()->json([
            $key => $paginator->items(),
            'data' => $paginator->items(),
            'meta' => [
                'total' => $paginator->total(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
            ],
        ]);
    }

    private function limit(Request $request): int
    {
        return min(max($request->integer('limit', 10), 1), 100);
    }
}
