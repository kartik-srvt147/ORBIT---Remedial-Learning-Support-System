<?php

namespace App\Services;

use App\Models\RemedialAction;
use App\Models\Student;
use Illuminate\Support\Collection;

class RecommendationService
{
    public function __construct(
        private readonly SlowLearnerService $slowLearnerService
    ) {
    }

    public function getRecommendations(int $studentId): Collection
    {
        $recommendations = collect();

        $weakSubjects = $this->slowLearnerService->getWeakSubjects($studentId);
        $attendancePercentage = $this->slowLearnerService->getAttendancePercentage($studentId);

        $recommendations = $recommendations->concat($this->buildWeakSubjectRecommendations($studentId, $weakSubjects));

        if ($attendancePercentage < 75) {
            $recommendations->push([
                'student_id' => $studentId,
                'subject_id' => null,
                'action_type' => 'counseling',
                'description' => sprintf(
                    'Attendance is %.2f%%, below the 75%% threshold. Schedule counseling with student and guardian.',
                    $attendancePercentage
                ),
            ]);
        }

        return $recommendations->values();
    }

    public function saveRecommendations(int $studentId): Collection
    {
        $recommendations = $this->getRecommendations($studentId);

        return $recommendations->map(function (array $recommendation) {
            return RemedialAction::query()->updateOrCreate(
                [
                    'student_id' => $recommendation['student_id'],
                    'subject_id' => $recommendation['subject_id'],
                    'action_type' => $recommendation['action_type'],
                ],
                [
                    'description' => $recommendation['description'],
                ]
            );
        })->values();
    }

    public function generateForAllStudents(): int
    {
        $processedCount = 0;

        Student::query()
            ->select('id')
            ->chunkById(200, function ($students) use (&$processedCount): void {
                foreach ($students as $student) {
                    $this->saveRecommendations((int) $student->id);
                    $processedCount++;
                }
            });

        return $processedCount;
    }

    private function buildWeakSubjectRecommendations(int $studentId, Collection $weakSubjects): Collection
    {
        return $weakSubjects->flatMap(function ($subject) use ($studentId): array {
            $subjectId = (int) $subject->subject_id;
            $subjectName = (string) ($subject->subject_name ?? 'Subject');
            $subjectPercentage = (float) ($subject->average_percentage ?? 0);

            return [
                [
                    'student_id' => $studentId,
                    'subject_id' => $subjectId,
                    'action_type' => 'extra_class',
                    'description' => sprintf(
                        '%s performance is %.2f%% (below 40%%). Enroll in extra support class.',
                        $subjectName,
                        $subjectPercentage
                    ),
                ],
                [
                    'student_id' => $studentId,
                    'subject_id' => $subjectId,
                    'action_type' => 'practice_assignment',
                    'description' => sprintf(
                        '%s performance is %.2f%% (below 40%%). Assign targeted practice work.',
                        $subjectName,
                        $subjectPercentage
                    ),
                ],
            ];
        })->values();
    }
}
