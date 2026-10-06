<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StudentDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $student = DB::table('students')
            ->join('batches', 'batches.batch_id', '=', 'students.batch_id')
            ->join('programs', 'programs.program_id', '=', 'batches.program_id')
            ->where('students.user_id', $user->getKey())
            ->first([
                'students.student_id',
                'students.student_code',
                'students.admitted_on',
                'students.batch_id',
                'batches.program_id',
                'batches.intake_year',
                'programs.program_name',
            ]);

        $currentTerm = DB::table('academic_terms')
            ->where('is_current', true)
            ->first(['term_id', 'term_code', 'term_name', 'start_date', 'end_date']);
        $studentId = $student?->student_id;

        $courses = $studentId === null
            ? collect()
            : $this->courses((int) $studentId, isset($currentTerm->term_id) ? (int) $currentTerm->term_id : null);
        $courseIds = array_values($courses->pluck('class_id')->map(fn (mixed $id): int => (int) $id)->all());
        $toDo = $studentId === null
            ? collect()
            : $this->toDo((int) $studentId, $courseIds);
        $feedback = $studentId === null
            ? collect()
            : $this->recentFeedback((int) $studentId);
        $upcomingExams = $studentId === null
            ? collect()
            : $this->upcomingExams((int) $studentId, $courseIds);

        $gradedPercentages = $courses
            ->pluck('standing')
            ->filter(fn (mixed $standing): bool => is_numeric($standing))
            ->map(fn (mixed $standing): float => (float) $standing);
        $gpa = $gradedPercentages->isEmpty()
            ? null
            : round($gradedPercentages->average(fn (float $percentage): float => $this->gradePoint($percentage)), 2);

        return Inertia::render('student/Dashboard', [
            'studentDashboard' => [
                'student' => [
                    'name' => $user->name,
                    'studentCode' => $student->student_code ?? 'Profile pending',
                    'program' => $student->program_name ?? 'Student profile not linked',
                    'intakeYear' => $student->intake_year ?? null,
                ],
                'term' => [
                    'label' => $currentTerm->term_name ?? 'No active term',
                    'code' => $currentTerm->term_code ?? 'Term pending',
                ],
                'summary' => [
                    ['label' => 'Current GPA', 'value' => $gpa === null ? '—' : number_format($gpa, 2), 'icon' => 'course-01.svg'],
                    ['label' => 'Enrolled Credits', 'value' => number_format((float) $courses->sum('credit_hours'), 1), 'icon' => 'course-02.svg'],
                    ['label' => 'Pending Tasks', 'value' => $toDo->whereIn('status', ['Due', 'Missing', 'Late'])->count().' Due', 'icon' => 'course-03.svg', 'warning' => true],
                    ['label' => 'Upcoming Exams', 'value' => $upcomingExams->count().' Upcoming', 'icon' => 'course-04.svg'],
                ],
                'courses' => $courses->values(),
                'toDo' => $toDo->values(),
                'feedback' => $feedback->values(),
                'examAlert' => $upcomingExams->first(),
                'quickLinks' => [
                    ['label' => 'Academic Calendar', 'icon' => 'widget-12.svg'],
                    ['label' => 'Library Course Reserves', 'icon' => 'widget-13.svg'],
                    ['label' => 'Writing Center Tutoring', 'icon' => 'widget-14.svg'],
                    ['label' => 'IT Help Desk & Software Hub', 'icon' => 'widget-15.svg'],
                ],
            ],
        ]);
    }

    /** @return Collection<int, array{class_id: int, code: string, title: string, section: string, term: string, instructor: string, credit_hours: float, standing: float|null, letter: string, assessments: int, discussions: int, files: int, theme: int}> */
    private function courses(int $studentId, ?int $termId): Collection
    {
        return DB::table('enrollments')
            ->join('classes', 'classes.class_id', '=', 'enrollments.class_id')
            ->join('subjects', 'subjects.subject_id', '=', 'classes.subject_id')
            ->join('academic_terms', 'academic_terms.term_id', '=', 'classes.term_id')
            ->leftJoin('class_staff', function ($join): void {
                $join->on('class_staff.class_id', '=', 'classes.class_id')->where('class_staff.is_primary', true);
            })
            ->leftJoin('teachers', 'teachers.teacher_id', '=', 'class_staff.teacher_id')
            ->leftJoin('users as instructors', 'instructors.user_id', '=', 'teachers.user_id')
            ->where('enrollments.student_id', $studentId)
            ->where('enrollments.status', 'ENROLLED')
            ->when($termId !== null, fn ($query) => $query->where('classes.term_id', $termId))
            ->orderBy('subjects.subject_code')
            ->get([
                'classes.class_id', 'classes.crn_code', 'classes.section_name',
                'subjects.subject_code', 'subjects.subject_title', 'subjects.credit_hours',
                'academic_terms.term_name', 'instructors.full_name as instructor_name',
            ])
            ->map(function (object $course, int $index) use ($studentId): array {
                $grade = DB::table('submissions')
                    ->join('submission_grades', 'submission_grades.submission_id', '=', 'submissions.submission_id')
                    ->join('assessments', 'assessments.assessment_id', '=', 'submissions.assessment_id')
                    ->where('submissions.student_id', $studentId)
                    ->where('submissions.class_id', $course->class_id)
                    ->where('submission_grades.is_published', true)
                    ->selectRaw('SUM(submission_grades.final_marks) as earned, SUM(assessments.max_marks) as possible')
                    ->first();
                $standing = $grade !== null && (float) $grade->possible > 0
                    ? round(((float) $grade->earned / (float) $grade->possible) * 100, 1)
                    : null;
                $room = DB::table('class_meetings')->where('class_id', $course->class_id)->orderBy('day_of_week')->value('room');

                return [
                    'class_id' => (int) $course->class_id,
                    'code' => (string) $course->subject_code,
                    'title' => (string) $course->subject_title,
                    'section' => $course->crn_code.' · '.($room ?: $course->section_name),
                    'term' => (string) $course->term_name,
                    'instructor' => (string) ($course->instructor_name ?? 'Instructor pending'),
                    'credit_hours' => (float) $course->credit_hours,
                    'standing' => $standing,
                    'letter' => $standing === null ? '—' : $this->letterGrade($standing),
                    'assessments' => DB::table('assessments')->where('class_id', $course->class_id)->where('is_published', true)->count(),
                    'discussions' => DB::table('discussion_threads')->where('class_id', $course->class_id)->where('status', 'OPEN')->count(),
                    'files' => DB::table('course_files')->where('class_id', $course->class_id)->where('status', 'PUBLISHED')->count(),
                    'theme' => $index % 6,
                ];
            });
    }

    /**
     * @param  list<int>  $classIds
     * @return Collection<int, array{id: int, title: string, course: string, points: string, status: string, due: string}>
     */
    private function toDo(int $studentId, array $classIds): Collection
    {
        if ($classIds === []) {
            return collect();
        }

        $latestSubmissions = DB::table('submissions')
            ->where('student_id', $studentId)
            ->selectRaw('assessment_id, MAX(submitted_at) as submitted_at')
            ->groupBy('assessment_id');

        return DB::table('assessments')
            ->join('classes', 'classes.class_id', '=', 'assessments.class_id')
            ->join('subjects', 'subjects.subject_id', '=', 'classes.subject_id')
            ->leftJoinSub($latestSubmissions, 'latest_submissions', fn ($join) => $join->on('latest_submissions.assessment_id', '=', 'assessments.assessment_id'))
            ->leftJoin('assessment_exceptions', function ($join) use ($studentId): void {
                $join->on('assessment_exceptions.assessment_id', '=', 'assessments.assessment_id')
                    ->where('assessment_exceptions.student_id', $studentId);
            })
            ->whereIn('assessments.class_id', $classIds)
            ->where('assessments.is_published', true)
            ->orderBy('assessments.due_at')
            ->limit(8)
            ->get([
                'assessments.assessment_id', 'assessments.title', 'assessments.max_marks',
                'assessments.due_at', 'subjects.subject_code', 'latest_submissions.submitted_at',
                'assessment_exceptions.extended_due_at', 'assessment_exceptions.is_excused',
            ])
            ->map(function (object $item): array {
                $dueAt = Carbon::parse($item->extended_due_at ?? $item->due_at);
                $submittedAt = $item->submitted_at === null ? null : Carbon::parse($item->submitted_at);
                $status = match (true) {
                    (bool) $item->is_excused => 'Excused',
                    $submittedAt !== null && $submittedAt->greaterThan($dueAt) => 'Late',
                    $submittedAt !== null => 'Submitted',
                    $dueAt->isPast() => 'Missing',
                    default => 'Due',
                };

                return [
                    'id' => (int) $item->assessment_id,
                    'title' => (string) $item->title,
                    'course' => (string) $item->subject_code,
                    'points' => number_format((float) $item->max_marks, 0).' pts',
                    'status' => $status,
                    'due' => $dueAt->isToday() ? 'Today at '.$dueAt->format('g:i A') : $dueAt->diffForHumans(),
                ];
            });
    }

    /** @return Collection<int, array{title: string, course: string, score: string, feedback: string, time: string}> */
    private function recentFeedback(int $studentId): Collection
    {
        return DB::table('submission_grades')
            ->join('submissions', 'submissions.submission_id', '=', 'submission_grades.submission_id')
            ->join('assessments', 'assessments.assessment_id', '=', 'submissions.assessment_id')
            ->join('classes', 'classes.class_id', '=', 'submissions.class_id')
            ->join('subjects', 'subjects.subject_id', '=', 'classes.subject_id')
            ->where('submissions.student_id', $studentId)
            ->where('submission_grades.is_published', true)
            ->latest('submission_grades.graded_at')
            ->limit(2)
            ->get([
                'assessments.title', 'assessments.max_marks', 'subjects.subject_code',
                'submission_grades.final_marks', 'submission_grades.feedback', 'submission_grades.graded_at',
            ])
            ->map(fn (object $grade): array => [
                'title' => (string) $grade->title,
                'course' => (string) $grade->subject_code,
                'score' => number_format((float) $grade->final_marks, 0).' / '.number_format((float) $grade->max_marks, 0),
                'feedback' => (string) ($grade->feedback ?: 'No written feedback was provided.'),
                'time' => Carbon::parse($grade->graded_at)->diffForHumans(),
            ]);
    }

    /**
     * @param  list<int>  $classIds
     * @return Collection<int, array{title: string, detail: string}>
     */
    private function upcomingExams(int $studentId, array $classIds): Collection
    {
        if ($classIds === []) {
            return collect();
        }

        return DB::table('assessments')
            ->join('classes', 'classes.class_id', '=', 'assessments.class_id')
            ->join('subjects', 'subjects.subject_id', '=', 'classes.subject_id')
            ->leftJoin('assessment_exceptions', function ($join) use ($studentId): void {
                $join->on('assessment_exceptions.assessment_id', '=', 'assessments.assessment_id')
                    ->where('assessment_exceptions.student_id', $studentId);
            })
            ->whereIn('assessments.class_id', $classIds)
            ->where('assessments.assessment_type', 'EXAM')
            ->where('assessments.is_published', true)
            ->where('assessments.due_at', '>=', now())
            ->orderBy('assessments.due_at')
            ->get(['assessments.title', 'assessments.due_at', 'assessment_exceptions.extended_due_at'])
            ->map(function (object $exam): array {
                $date = Carbon::parse($exam->extended_due_at ?? $exam->due_at);

                return [
                    'title' => (string) $exam->title,
                    'detail' => $date->format('M j · g:i A'),
                ];
            });
    }

    private function letterGrade(float $percentage): string
    {
        return match (true) {
            $percentage >= 90 => 'A',
            $percentage >= 80 => 'B+',
            $percentage >= 70 => 'B',
            $percentage >= 60 => 'C',
            default => 'F',
        };
    }

    private function gradePoint(float $percentage): float
    {
        return match (true) {
            $percentage >= 90 => 4.0,
            $percentage >= 80 => 3.0,
            $percentage >= 70 => 2.0,
            $percentage >= 60 => 1.0,
            default => 0.0,
        };
    }
}
