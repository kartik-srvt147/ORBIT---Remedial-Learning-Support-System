<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\Student;
use App\Models\StudentFlag;
use App\Models\TeacherNotification;
use App\Services\RecommendationService;
use App\Services\SlowLearnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function getSummary(Request $request): JsonResponse
    {
        $studentQuery = $this->applyStudentFilters(Student::query(), $request);
        $studentIds = (clone $studentQuery)->pluck('students.id');

        $totalStudents = $studentIds->count();

        $totalSlowLearners = StudentFlag::query()
            ->whereIn('student_id', $studentIds)
            ->where('is_slow_learner', true)
            ->distinct()
            ->count('student_id');

        $lowAttendanceSubquery = DB::table('attendance')
            ->select('student_id')
            ->whereIn('student_id', $studentIds)
            ->groupBy('student_id')
            ->havingRaw("((SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) * 100.0) / COUNT(*)) < 75");

        $lowAttendanceCount = DB::query()
            ->fromSub($lowAttendanceSubquery, 't')
            ->count();

        $totalNormalStudents = max($totalStudents - $totalSlowLearners, 0);

        return response()->json([
            'total_students' => $totalStudents,
            'total_slow_learners' => $totalSlowLearners,
            'total_normal_students' => $totalNormalStudents,
            'low_attendance_students' => $lowAttendanceCount,
        ]);
    }

    public function getStudents(Request $request, SlowLearnerService $slowLearnerService): JsonResponse
    {
        $query = $this->applyStudentFilters(
            Student::query()
                ->select('id', 'name', 'class', 'section', 'roll_number', 'teacher_id')
                ->with(['teacher:id,name,email', 'flag:id,student_id,is_slow_learner']),
            $request
        )->orderBy('name');

        $paginator = $query->paginate(
            perPage: $this->limit($request),
            page: $this->page($request)
        );

        $items = $paginator->getCollection()
            ->map(fn (Student $student): array => $this->studentPerformancePayload($student, $slowLearnerService))
            ->values();

        return $this->paginatedResponse($paginator, $items, 'students');
    }

    public function getSlowLearners(Request $request, SlowLearnerService $slowLearnerService): JsonResponse
    {
        // Keep flags fresh for demo/testing endpoints.
        $slowLearnerService->evaluateAllStudents();

        $query = $this->applyStudentFilters(
            Student::query()
                ->select('id', 'name', 'class', 'section', 'roll_number', 'teacher_id')
                ->with(['teacher:id,name,email']),
            $request
        )
            ->whereHas('flag', function ($query): void {
                $query->where('is_slow_learner', true);
            })
            ->orderBy('name');

        $paginator = $query->paginate(
            perPage: $this->limit($request),
            page: $this->page($request)
        );

        $items = $paginator->getCollection()
            ->map(fn (Student $student): array => $this->studentPerformancePayload($student, $slowLearnerService))
            ->values();

        return $this->paginatedResponse($paginator, $items, 'slow_learners');
    }

    public function getRecommendations(Request $request, RecommendationService $recommendationService): JsonResponse
    {
        $recommendationService->generateForAllStudents();

        $query = $this->applyStudentFilters(
            Student::query()
                ->select('id', 'name', 'class', 'section', 'roll_number', 'teacher_id')
                ->with([
                'teacher:id,name,email',
                'remedialActions' => function ($query): void {
                    $query->select('id', 'student_id', 'subject_id', 'action_type', 'description', 'created_at')
                        ->orderBy('created_at', 'desc');
                },
                'remedialActions.subject:id,name',
            ]),
            $request
        )
            ->whereHas('remedialActions')
            ->orderBy('name');

        $paginator = $query->paginate(
            perPage: $this->limit($request),
            page: $this->page($request)
        );

        $items = $paginator->getCollection()
            ->map(fn (Student $student): array => [
                'student' => [
                    'id' => $student->id,
                    'name' => $student->name,
                    'class' => $student->class,
                    'section' => $student->section,
                    'roll_number' => $student->roll_number,
                    'teacher' => $student->teacher,
                ],
                'recommendations' => $student->remedialActions->map(fn ($action): array => [
                    'id' => $action->id,
                    'subject_id' => $action->subject_id,
                    'subject_name' => $action->subject?->name,
                    'action_type' => $action->action_type,
                    'description' => $action->description,
                    'created_at' => optional($action->created_at)->toISOString(),
                ])->values(),
            ])
            ->values();

        return $this->paginatedResponse($paginator, $items, 'data');
    }

    public function getPerformanceTrends(Request $request, Student $student): JsonResponse
    {
        $query = Assessment::query()
            ->join('subjects', 'subjects.id', '=', 'assessments.subject_id')
            ->where('assessments.student_id', $student->id);

        $this->applySubjectFilter($query, $request);

        $points = $query
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
            'data' => $points,
            'chart' => [
                'x_axis' => 'date',
                'y_axis' => 'percentage',
                'series' => 'subject_name',
            ],
        ]);
    }

    public function getStudentDetails(
        Student $student,
        SlowLearnerService $slowLearnerService,
        RecommendationService $recommendationService
    ): JsonResponse {
        $recommendationService->saveRecommendations((int) $student->id);

        $student->load([
            'teacher:id,name,email',
            'assessments.subject:id,name',
            'remedialActions' => function ($query): void {
                $query->select('id', 'student_id', 'subject_id', 'action_type', 'description', 'created_at')
                    ->orderByDesc('created_at');
            },
            'remedialActions.subject:id,name',
        ]);

        return response()->json([
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'class' => $student->class,
                'section' => $student->section,
                'roll_number' => $student->roll_number,
                'teacher' => $student->teacher,
            ],
            'average_marks' => $slowLearnerService->getAverageMarks((int) $student->id),
            'attendance_percentage' => $slowLearnerService->getAttendancePercentage((int) $student->id),
            'weak_subjects' => $slowLearnerService->getWeakSubjects((int) $student->id),
            'assessments' => $student->assessments,
            'recommendations' => $student->remedialActions,
        ]);
    }

    public function getAnalytics(Request $request): JsonResponse
    {
        $studentIds = $this->applyStudentFilters(Student::query(), $request)
            ->pluck('students.id');

        $assessmentQuery = Assessment::query()
            ->whereIn('student_id', $studentIds);

        $this->applySubjectFilter($assessmentQuery, $request);

        $subjectAverages = (clone $assessmentQuery)
            ->join('subjects', 'subjects.id', '=', 'assessments.subject_id')
            ->groupBy('subjects.id', 'subjects.name')
            ->orderBy('subjects.name')
            ->selectRaw('
                subjects.id as subject_id,
                subjects.name as subject_name,
                ROUND((SUM(assessments.marks_obtained) / NULLIF(SUM(assessments.max_marks), 0)) * 100, 2) as average_percentage
            ')
            ->get();

        $topStudents = (clone $assessmentQuery)
            ->join('students', 'students.id', '=', 'assessments.student_id')
            ->groupBy('students.id', 'students.name', 'students.class', 'students.section', 'students.roll_number')
            ->orderByDesc('average_percentage')
            ->limit(10)
            ->selectRaw('
                students.id as student_id,
                students.name as student_name,
                students.class,
                students.section,
                students.roll_number,
                ROUND((SUM(assessments.marks_obtained) / NULLIF(SUM(assessments.max_marks), 0)) * 100, 2) as average_percentage
            ')
            ->get();

        $weakSubjects = (clone $assessmentQuery)
            ->join('subjects', 'subjects.id', '=', 'assessments.subject_id')
            ->groupBy('subjects.id', 'subjects.name')
            ->havingRaw('(SUM(assessments.marks_obtained) / NULLIF(SUM(assessments.max_marks), 0)) * 100 < 40')
            ->orderBy('average_percentage')
            ->selectRaw('
                subjects.id as subject_id,
                subjects.name as subject_name,
                ROUND((SUM(assessments.marks_obtained) / NULLIF(SUM(assessments.max_marks), 0)) * 100, 2) as average_percentage,
                COUNT(DISTINCT assessments.student_id) as affected_students
            ')
            ->get();

        return response()->json([
            'filters' => $request->only(['class', 'section', 'subject', 'search']),
            'charts' => [
                'subject_wise_average_performance' => $subjectAverages,
                'top_performing_students' => $topStudents,
                'most_weak_subjects' => $weakSubjects,
            ],
        ]);
    }

    public function getNotifications(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = TeacherNotification::query()
            ->with(['student:id,name,class,section,roll_number'])
            ->orderByDesc('created_at');

        if ($user->role !== 'admin') {
            $query->where('teacher_id', $user->id);
        } elseif ($request->filled('teacher_id')) {
            $query->where('teacher_id', $request->integer('teacher_id'));
        }

        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        $paginator = $query->paginate(
            perPage: $this->limit($request),
            page: $this->page($request)
        );

        $items = $paginator->getCollection()
            ->map(fn (TeacherNotification $notification): array => [
                'id' => $notification->id,
                'type' => $notification->type,
                'title' => $notification->title,
                'message' => $notification->message,
                'read_at' => optional($notification->read_at)->toISOString(),
                'created_at' => optional($notification->created_at)->toISOString(),
                'student' => $notification->student,
            ])
            ->values();

        return $this->paginatedResponse($paginator, $items, 'notifications');
    }

    private function applyStudentFilters($query, Request $request)
    {
        if ($request->filled('class')) {
            $query->where('students.class', (string) $request->string('class'));
        }

        if ($request->filled('section')) {
            $query->where('students.section', (string) $request->string('section'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($query) use ($search): void {
                $query->where('students.name', 'like', "%{$search}%")
                    ->orWhere('students.roll_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('subject')) {
            $subject = trim((string) $request->query('subject'));
            $query->whereHas('assessments.subject', function ($query) use ($subject): void {
                if (ctype_digit($subject)) {
                    $query->where('subjects.id', (int) $subject);

                    return;
                }

                $query->where('subjects.name', 'like', "%{$subject}%");
            });
        }

        return $query;
    }

    private function applySubjectFilter($query, Request $request): void
    {
        if (! $request->filled('subject')) {
            return;
        }

        $subject = trim((string) $request->query('subject'));

        if (ctype_digit($subject)) {
            $query->where('assessments.subject_id', (int) $subject);

            return;
        }

        $query->whereHas('subject', function ($query) use ($subject): void {
            $query->where('subjects.name', 'like', "%{$subject}%");
        });
    }

    private function studentPerformancePayload(Student $student, SlowLearnerService $slowLearnerService): array
    {
        return [
            'student_id' => $student->id,
            'student_name' => $student->name,
            'class' => $student->class,
            'section' => $student->section,
            'roll_number' => $student->roll_number,
            'teacher' => $student->teacher,
            'average_marks' => $slowLearnerService->getAverageMarks($student->id),
            'attendance_percentage' => $slowLearnerService->getAttendancePercentage($student->id),
            'weak_subjects' => $slowLearnerService->getWeakSubjects($student->id),
            'is_slow_learner' => (bool) $student->flag?->is_slow_learner,
        ];
    }

    private function paginatedResponse(LengthAwarePaginator $paginator, Collection $items, string $key): JsonResponse
    {
        $payload = [
            $key => $items,
            'meta' => [
                'total' => $paginator->total(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
            ],
        ];

        if ($key !== 'data') {
            $payload['data'] = $items;
        }

        return response()->json($payload);
    }

    private function limit(Request $request): int
    {
        return min(max($request->integer('limit', 10), 1), 100);
    }

    private function page(Request $request): int
    {
        return max($request->integer('page', 1), 1);
    }
}
