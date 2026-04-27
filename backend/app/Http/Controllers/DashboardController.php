<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\StudentFlag;
use App\Services\RecommendationService;
use App\Services\SlowLearnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function getSummary(): JsonResponse
    {
        $totalStudents = Student::query()->count();

        $totalSlowLearners = StudentFlag::query()
            ->where('is_slow_learner', true)
            ->distinct()
            ->count('student_id');

        $lowAttendanceSubquery = DB::table('attendance')
            ->select('student_id')
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

    public function getSlowLearners(SlowLearnerService $slowLearnerService): JsonResponse
    {
        // Keep flags fresh for demo/testing endpoints.
        $slowLearnerService->evaluateAllStudents();

        $slowLearners = Student::query()
            ->select('id', 'name')
            ->whereHas('flag', function ($query): void {
                $query->where('is_slow_learner', true);
            })
            ->orderBy('name')
            ->get()
            ->map(function (Student $student) use ($slowLearnerService): array {
                return [
                    'student_id' => $student->id,
                    'student_name' => $student->name,
                    'average_marks' => $slowLearnerService->getAverageMarks($student->id),
                    'attendance_percentage' => $slowLearnerService->getAttendancePercentage($student->id),
                    'weak_subjects' => $slowLearnerService->getWeakSubjects($student->id),
                ];
            })
            ->values();

        return response()->json([
            'count' => $slowLearners->count(),
            'slow_learners' => $slowLearners,
        ]);
    }

    public function getRecommendations(RecommendationService $recommendationService): JsonResponse
    {
        $recommendationService->generateForAllStudents();

        $students = Student::query()
            ->select('id', 'name', 'class', 'section', 'roll_number', 'teacher_id')
            ->with([
                'teacher:id,name,email',
                'remedialActions' => function ($query): void {
                    $query->select('id', 'student_id', 'subject_id', 'action_type', 'description', 'created_at')
                        ->orderBy('created_at', 'desc');
                },
                'remedialActions.subject:id,name',
            ])
            ->orderBy('name')
            ->get()
            ->map(function (Student $student): array {
                return [
                    'student' => [
                        'id' => $student->id,
                        'name' => $student->name,
                        'class' => $student->class,
                        'section' => $student->section,
                        'roll_number' => $student->roll_number,
                        'teacher' => $student->teacher,
                    ],
                    'recommendations' => $student->remedialActions->map(function ($action): array {
                        return [
                            'id' => $action->id,
                            'subject_id' => $action->subject_id,
                            'subject_name' => $action->subject?->name,
                            'action_type' => $action->action_type,
                            'description' => $action->description,
                            'created_at' => optional($action->created_at)->toISOString(),
                        ];
                    })->values(),
                ];
            })
            ->values();

        return response()->json([
            'count' => $students->count(),
            'data' => $students,
        ]);
    }
}

