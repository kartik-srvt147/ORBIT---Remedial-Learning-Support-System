<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\SlowLearnerService;
use Illuminate\Http\JsonResponse;

class SlowLearnerTestController extends Controller
{
    public function index(SlowLearnerService $slowLearnerService): JsonResponse
    {
        // Ensure all flags are up-to-date before fetching slow learners.
        $slowLearnerService->evaluateAllStudents();

        $slowLearners = Student::query()
            ->select('id', 'name', 'class', 'section', 'roll_number')
            ->whereHas('flag', function ($query): void {
                $query->where('is_slow_learner', true);
            })
            ->orderBy('name')
            ->get()
            ->map(function (Student $student) use ($slowLearnerService): array {
                return [
                    'student_id' => $student->id,
                    'name' => $student->name,
                    'class' => $student->class,
                    'section' => $student->section,
                    'roll_number' => $student->roll_number,
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
}
