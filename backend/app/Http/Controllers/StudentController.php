<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Services\RecommendationService;
use App\Services\SlowLearnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function getMyData(
        Request $request,
        SlowLearnerService $slowLearnerService,
        RecommendationService $recommendationService
    ): JsonResponse
    {
        $user = $request->user();

        $student = $user?->student()
            ->with([
                'assessments.subject:id,name',
                'remedialActions.subject:id,name',
                'sections.teacherSubjects.subject:id,name',
                'sections.teacherSubjects.teacher:id,name,email',
            ])
            ->first();

        if (! $student) {
            return response()->json([
                'message' => 'No linked student record found for this user.',
            ], 404);
        }

        // Explicit ownership check to ensure student only accesses own data.
        if ((int) $student->user_id !== (int) $user->id) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        $recommendationService->saveRecommendations((int) $student->id);
        $student->load('remedialActions.subject:id,name');

        $totalAttendance = Attendance::query()
            ->where('student_id', $student->id)
            ->count();

        $presentAttendance = Attendance::query()
            ->where('student_id', $student->id)
            ->where('status', 'present')
            ->count();

        $attendancePercentage = $totalAttendance > 0
            ? round(($presentAttendance / $totalAttendance) * 100, 2)
            : 0.0;

        $sections = $student->sections->map(fn ($section): array => [
            'id' => $section->id,
            'class_name' => $section->class_name,
            'section_name' => $section->section_name,
            'subjects' => $section->teacherSubjects
                ->map(fn ($assignment): array => [
                    'subject' => $assignment->subject,
                    'teacher' => $assignment->teacher,
                ])
                ->values(),
        ])->values();

        return response()->json([
            'student' => $student->only([
                'id',
                'user_id',
                'teacher_id',
                'name',
                'class',
                'section',
                'roll_number',
                'created_at',
                'updated_at',
            ]),
            'sections' => $sections,
            'assessments' => $student->assessments,
            'average_marks' => $slowLearnerService->getAverageMarks((int) $student->id),
            'attendance_percentage' => $attendancePercentage,
            'weak_subjects' => $slowLearnerService->getWeakSubjects((int) $student->id),
            'recommendations' => $student->remedialActions,
        ]);
    }
}
