<?php

namespace App\Http\Controllers;

use App\Models\RemedialAction;
use App\Models\Student;
use App\Models\TeacherSubject;
use App\Services\SlowLearnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TeacherController extends Controller
{
    public function getAssignedSections(Request $request): JsonResponse
    {
        $sections = TeacherSubject::query()
            ->where('teacher_id', $request->user()->id)
            ->with(['section:id,class_name,section_name', 'subject:id,name'])
            ->get()
            ->groupBy('section_id')
            ->map(function ($assignments): array {
                $section = $assignments->first()->section;

                return [
                    'id' => $section->id,
                    'class_name' => $section->class_name,
                    'section_name' => $section->section_name,
                    'subjects' => $assignments
                        ->map(fn (TeacherSubject $assignment) => $assignment->subject)
                        ->values(),
                ];
            })
            ->values();

        return response()->json([
            'sections' => $sections,
        ]);
    }

    public function getStudents(Request $request, SlowLearnerService $slowLearnerService): JsonResponse
    {
        $sectionIds = $this->assignedSectionIds($request);

        $students = Student::query()
            ->select('students.id', 'students.name', 'students.class', 'students.section', 'students.roll_number', 'students.teacher_id')
            ->whereHas('sections', fn ($query) => $query->whereIn('sections.id', $sectionIds))
            ->with(['sections:id,class_name,section_name', 'flag:id,student_id,is_slow_learner'])
            ->orderBy('students.name')
            ->get()
            ->map(fn (Student $student): array => $this->studentPerformancePayload($student, $slowLearnerService))
            ->values();

        return response()->json([
            'students' => $students,
        ]);
    }

    public function getStudentPerformance(Request $request, Student $student, SlowLearnerService $slowLearnerService): JsonResponse
    {
        if (! $this->teacherCanAccessStudent($request, $student)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $student->load([
            'sections:id,class_name,section_name',
            'assessments.subject:id,name',
            'remedialActions.subject:id,name',
        ]);

        $trends = $student->assessments()
            ->join('subjects', 'subjects.id', '=', 'assessments.subject_id')
            ->groupBy('assessments.exam_date', 'subjects.id', 'subjects.name')
            ->orderBy('assessments.exam_date')
            ->selectRaw('
                assessments.exam_date as date,
                subjects.id as subject_id,
                subjects.name as subject_name,
                SUM(assessments.marks_obtained) as marks_obtained,
                SUM(assessments.max_marks) as max_marks,
                ROUND((SUM(assessments.marks_obtained) / NULLIF(SUM(assessments.max_marks), 0)) * 100, 2) as percentage
            ')
            ->get();

        return response()->json([
            'student' => $student->only(['id', 'name', 'class', 'section', 'roll_number']),
            'sections' => $student->sections,
            'average_marks' => $slowLearnerService->getAverageMarks((int) $student->id),
            'attendance_percentage' => $slowLearnerService->getAttendancePercentage((int) $student->id),
            'weak_subjects' => $slowLearnerService->getWeakSubjects((int) $student->id),
            'assessments' => $student->assessments,
            'performance_trends' => $trends,
            'recommendations' => $student->remedialActions,
        ]);
    }

    public function getSubjectPerformance(Request $request): JsonResponse
    {
        $sectionIds = $this->assignedSectionIds($request);

        $studentIds = Student::query()
            ->whereHas('sections', fn ($query) => $query->whereIn('sections.id', $sectionIds))
            ->pluck('students.id');

        $data = DB::table('assessments')
            ->join('subjects', 'subjects.id', '=', 'assessments.subject_id')
            ->whereIn('assessments.student_id', $studentIds)
            ->whereIn('assessments.subject_id', $this->assignedSubjectIds($request))
            ->groupBy('subjects.id', 'subjects.name')
            ->orderBy('subjects.name')
            ->selectRaw('
                subjects.id as subject_id,
                subjects.name as subject_name,
                ROUND((SUM(assessments.marks_obtained) / NULLIF(SUM(assessments.max_marks), 0)) * 100, 2) as average_percentage,
                COUNT(DISTINCT assessments.student_id) as student_count
            ')
            ->get();

        return response()->json([
            'subject_performance' => $data,
        ]);
    }


    public function addRemedialAction(Request $request, Student $student): JsonResponse
    {
        if (! $this->teacherCanAccessStudent($request, $student)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $assignedSubjectIds = $this->assignedSubjectIds($request);

        $data = $request->validate([
            'subject_id' => ['nullable', 'integer', Rule::in($assignedSubjectIds)],
            'action_type' => ['required', 'string', 'max:50'],
            'description' => ['required', 'string', 'max:2000'],
        ]);

        $action = RemedialAction::query()->create([
            'student_id' => $student->id,
            'subject_id' => $data['subject_id'] ?? null,
            'action_type' => $data['action_type'],
            'description' => $data['description'],
        ])->load('subject:id,name');

        return response()->json([
            'message' => 'Remedial action added successfully.',
            'remedial_action' => $action,
        ], 201);
    }

    private function assignedSectionIds(Request $request): array
    {
        return TeacherSubject::query()
            ->where('teacher_id', $request->user()->id)
            ->pluck('section_id')
            ->unique()
            ->values()
            ->all();
    }

    private function assignedSubjectIds(Request $request): array
    {
        return TeacherSubject::query()
            ->where('teacher_id', $request->user()->id)
            ->pluck('subject_id')
            ->unique()
            ->values()
            ->all();
    }

    private function teacherCanAccessStudent(Request $request, Student $student): bool
    {
        $sectionIds = $this->assignedSectionIds($request);

        if (empty($sectionIds)) {
            return false;
        }

        return DB::table('student_sections')
            ->where('student_id', $student->id)
            ->whereIn('section_id', $sectionIds)
            ->exists();
    }

    private function studentPerformancePayload(Student $student, SlowLearnerService $slowLearnerService): array
    {
        return [
            'student_id' => $student->id,
            'student_name' => $student->name,
            'class' => $student->class,
            'section' => $student->section,
            'roll_number' => $student->roll_number,
            'sections' => $student->sections,
            'average_marks' => $slowLearnerService->getAverageMarks((int) $student->id),
            'attendance_percentage' => $slowLearnerService->getAttendancePercentage((int) $student->id),
            'weak_subjects' => $slowLearnerService->getWeakSubjects((int) $student->id),
            'is_slow_learner' => (bool) $student->flag?->is_slow_learner,
        ];
    }
}
