<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function getMyData(Request $request): JsonResponse
    {
        $user = $request->user();

        $student = $user?->student()
            ->with([
                'assessments.subject:id,name',
                'remedialActions.subject:id,name',
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
            'assessments' => $student->assessments,
            'attendance_percentage' => $attendancePercentage,
            'recommendations' => $student->remedialActions,
        ]);
    }
}

