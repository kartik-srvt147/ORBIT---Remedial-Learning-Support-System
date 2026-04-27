<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\StudentFlag;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SlowLearnerService
{
    public function getAverageMarks(int $studentId): float
    {
        $row = Assessment::query()
            ->where('student_id', $studentId)
            ->selectRaw('SUM(marks_obtained) as total_obtained, SUM(max_marks) as total_max')
            ->first();

        if (! $row || (float) $row->total_max <= 0) {
            return 0.0;
        }

        return round(((float) $row->total_obtained / (float) $row->total_max) * 100, 2);
    }

    public function getAttendancePercentage(int $studentId): float
    {
        $totalDays = Attendance::query()
            ->where('student_id', $studentId)
            ->count();

        if ($totalDays === 0) {
            return 0.0;
        }

        $presentDays = Attendance::query()
            ->where('student_id', $studentId)
            ->where('status', 'present')
            ->count();

        return round(($presentDays / $totalDays) * 100, 2);
    }

    public function getWeakSubjects(int $studentId): Collection
    {
        return Assessment::query()
            ->join('subjects', 'subjects.id', '=', 'assessments.subject_id')
            ->where('assessments.student_id', $studentId)
            ->groupBy('subjects.id', 'subjects.name')
            ->select(
                'subjects.id as subject_id',
                'subjects.name as subject_name',
                DB::raw('ROUND((SUM(assessments.marks_obtained) / NULLIF(SUM(assessments.max_marks), 0)) * 100, 2) as average_percentage')
            )
            ->havingRaw('(SUM(assessments.marks_obtained) / NULLIF(SUM(assessments.max_marks), 0)) * 100 < 40')
            ->orderBy('average_percentage')
            ->get();
    }

    public function isSlowLearner(int $studentId): bool
    {
        $averageMarks = $this->getAverageMarks($studentId);
        $attendancePercentage = $this->getAttendancePercentage($studentId);

        return $averageMarks < 40 || $attendancePercentage < 75;
    }

    public function updateStudentFlag(int $studentId): StudentFlag
    {
        $isSlowLearner = $this->isSlowLearner($studentId);

        return StudentFlag::query()->updateOrCreate(
            ['student_id' => $studentId],
            [
                'is_slow_learner' => $isSlowLearner,
                'last_evaluated_at' => Carbon::now(),
            ]
        );
    }

    public function evaluateAllStudents(): int
    {
        $evaluatedCount = 0;

        Student::query()
            ->select('id')
            ->chunkById(200, function ($students) use (&$evaluatedCount): void {
                $studentIds = $students->pluck('id')->all();

                if (empty($studentIds)) {
                    return;
                }

                $assessmentStats = Assessment::query()
                    ->whereIn('student_id', $studentIds)
                    ->groupBy('student_id')
                    ->selectRaw('student_id, SUM(marks_obtained) as total_obtained, SUM(max_marks) as total_max')
                    ->get()
                    ->keyBy('student_id');

                $attendanceStats = Attendance::query()
                    ->whereIn('student_id', $studentIds)
                    ->groupBy('student_id')
                    ->selectRaw("student_id, COUNT(*) as total_days, SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days")
                    ->get()
                    ->keyBy('student_id');

                $now = Carbon::now();
                $rows = [];

                foreach ($studentIds as $studentId) {
                    $assessment = $assessmentStats->get($studentId);
                    $attendance = $attendanceStats->get($studentId);

                    $totalMax = (float) ($assessment->total_max ?? 0);
                    $totalObtained = (float) ($assessment->total_obtained ?? 0);
                    $averageMarks = $totalMax > 0 ? (($totalObtained / $totalMax) * 100) : 0.0;

                    $totalDays = (int) ($attendance->total_days ?? 0);
                    $presentDays = (int) ($attendance->present_days ?? 0);
                    $attendancePercentage = $totalDays > 0 ? (($presentDays / $totalDays) * 100) : 0.0;

                    $rows[] = [
                        'student_id' => $studentId,
                        'is_slow_learner' => $averageMarks < 40 || $attendancePercentage < 75,
                        'last_evaluated_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                StudentFlag::query()->upsert(
                    $rows,
                    ['student_id'],
                    ['is_slow_learner', 'last_evaluated_at', 'updated_at']
                );

                $evaluatedCount += count($rows);
            });

        return $evaluatedCount;
    }
}
