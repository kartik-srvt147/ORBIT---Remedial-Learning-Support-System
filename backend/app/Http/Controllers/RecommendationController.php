<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\RecommendationService;
use Illuminate\Http\JsonResponse;

class RecommendationController extends Controller
{
    public function index(RecommendationService $recommendationService): JsonResponse
    {
        // Ensure recommendations exist before returning them.
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

