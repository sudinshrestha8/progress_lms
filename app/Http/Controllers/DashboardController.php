<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        if ($request->user()->hasRole('student')) {
            return redirect()->route('student.dashboard');
        }

        $activeStudents = DB::table('students')->where('status', 'ACTIVE')->count();
        $activeTeachers = DB::table('teachers')->where('status', 'ACTIVE')->count();
        $activeClasses = DB::table('classes')->where('status', 'ACTIVE')->count();
        $submissionsToday = DB::table('submissions')->whereDate('submitted_at', today())->count();
        $pendingApprovals = DB::table('at_risk_flags')->whereIn('status', ['OPEN', 'ACKNOWLEDGED'])->count();

        $graded = DB::table('submission_grades')
            ->join('submissions', 'submissions.submission_id', '=', 'submission_grades.submission_id')
            ->join('assessments', 'assessments.assessment_id', '=', 'submissions.assessment_id')
            ->where('submission_grades.is_published', true)
            ->selectRaw('submission_grades.final_marks, assessments.max_marks')
            ->get();

        $percentages = $graded
            ->filter(fn (object $grade): bool => (float) $grade->max_marks > 0)
            ->map(fn (object $grade): float => round(((float) $grade->final_marks / (float) $grade->max_marks) * 100, 2));

        $passRate = $percentages->isEmpty()
            ? 0
            : round(($percentages->filter(fn (float $percentage): bool => $percentage >= 50)->count() / $percentages->count()) * 100, 1);

        $gradeDistribution = [
            ['grade' => 'A', 'percent' => $this->percentageInRange($percentages, 80, null), 'color' => '#0f766e'],
            ['grade' => 'B', 'percent' => $this->percentageInRange($percentages, 70, 80), 'color' => '#596579'],
            ['grade' => 'C', 'percent' => $this->percentageInRange($percentages, 60, 70), 'color' => '#77d1c8'],
            ['grade' => 'D', 'percent' => $this->percentageInRange($percentages, 50, 60), 'color' => '#ed9200'],
            ['grade' => 'F', 'percent' => $this->percentageInRange($percentages, 0, 50), 'color' => '#ba1a1a'],
        ];

        $departments = DB::table('departments')
            ->leftJoin('programs', 'programs.department_id', '=', 'departments.department_id')
            ->leftJoin('batches', 'batches.program_id', '=', 'programs.program_id')
            ->leftJoin('students', function ($join): void {
                $join->on('students.batch_id', '=', 'batches.batch_id')->where('students.status', 'ACTIVE');
            })
            ->groupBy('departments.department_id', 'departments.department_name')
            ->orderByDesc(DB::raw('COUNT(students.student_id)'))
            ->limit(6)
            ->get(['departments.department_name as name', DB::raw('COUNT(students.student_id) as value')]);

        $terms = DB::table('academic_terms')
            ->orderByDesc('start_date')
            ->get(['term_id as id', 'term_name as label', 'is_current'])
            ->map(fn (object $term): array => [
                'id' => (int) $term->id,
                'label' => $term->label.($term->is_current ? ' (Active)' : ''),
            ]);

        $user = $request->user();

        return Inertia::render('Dashboard', [
            'dashboard' => [
                'institution' => [
                    'name' => 'Progress LMS',
                    'scope' => 'University Admin',
                    'campus' => 'Main Campus (HQ)',
                    'term' => $terms->first()['label'] ?? 'No active term',
                    'context' => 'Institutional Administration',
                    'summary' => sprintf(
                        'Campus-wide academic metrics across %d departments and %s active LMS accounts.',
                        DB::table('departments')->count(),
                        number_format(DB::table('users')->where('status', 'ACTIVE')->count()),
                    ),
                ],
                'user' => [
                    'name' => $user->name,
                    'role' => 'Provost / LMS Admin',
                    'canManageRoles' => $user->hasRole('super_admin'),
                ],
                'terms' => $terms,
                'colleges' => [['id' => 'all', 'label' => 'All Colleges (Main Campus)']],
                'sync' => ['label' => 'SIS Sync', 'detail' => 'Live database connection'],
                'metrics' => [
                    ['label' => 'Total Students', 'value' => number_format($activeStudents), 'trend' => 'Live', 'detail' => 'Registered active', 'icon' => 'kpi-01.svg'],
                    ['label' => 'Total Teachers', 'value' => number_format($activeTeachers), 'trend' => 'Live', 'detail' => 'Active faculty', 'icon' => 'kpi-04.svg'],
                    ['label' => 'Active Classes', 'value' => number_format($activeClasses), 'trend' => 'Live', 'detail' => 'Current delivery', 'icon' => 'kpi-06.svg'],
                    ['label' => 'Avg Pass Rate', 'value' => $passRate.'%', 'trend' => 'Live', 'detail' => 'Published grades', 'icon' => 'kpi-08.svg'],
                    ['label' => 'Submissions Today', 'value' => number_format($submissionsToday), 'trend' => 'Today', 'detail' => 'Submitted on time', 'icon' => 'kpi-10.svg'],
                    ['label' => 'Pending Approvals', 'value' => number_format($pendingApprovals), 'trend' => $pendingApprovals.' urgent', 'detail' => 'Risk flags requiring review', 'icon' => 'kpi-12.svg', 'critical' => true],
                ],
                'departments' => $departments,
                'gradeDistribution' => $gradeDistribution,
                'gradedEnrollments' => $percentages->count(),
                'passRate' => $passRate,
                'activityTrend' => $this->activityTrend(),
                'attentionGroups' => $this->attentionGroups(),
                'recentActivity' => $this->recentActivity(),
                'adminActions' => [
                    ['label' => 'Add Teacher', 'icon' => 'bottom-07.svg'],
                    ['label' => 'Add Student', 'icon' => 'bottom-08.svg'],
                    ['label' => 'Create Course Shell', 'icon' => 'bottom-09.svg'],
                    ['label' => 'Import CSV (Roster / Users)', 'icon' => 'bottom-10.svg'],
                    ['label' => 'Post Global Announcement', 'icon' => 'bottom-11.svg', 'primary' => true],
                ],
                'systemHealth' => [
                    ['name' => 'Banner SIS Registrar', 'detail' => 'Database-backed student records', 'status' => 'Connected', 'icon' => 'bottom-01.svg'],
                    ['name' => 'Turnitin Integrity Engine', 'detail' => 'Assessment integration', 'status' => 'Online', 'icon' => 'bottom-02.svg'],
                    ['name' => 'Media Storage', 'detail' => config('filesystems.default').' disk', 'status' => 'Healthy', 'icon' => 'bottom-03.svg'],
                    ['name' => 'Campus SAML 2.0 / SSO', 'detail' => 'Authentication gateway', 'status' => 'Active', 'icon' => 'bottom-04.svg'],
                ],
            ],
        ]);
    }

    /** @param Collection<int, float> $percentages */
    private function percentageInRange($percentages, float $minimum, ?float $maximum): int
    {
        if ($percentages->isEmpty()) {
            return 0;
        }

        $count = $percentages->filter(
            fn (float $value): bool => $value >= $minimum && ($maximum === null || $value < $maximum),
        )->count();

        return (int) round(($count / $percentages->count()) * 100);
    }

    /** @return list<array{label: string, value: int}> */
    private function activityTrend(): array
    {
        return array_values(collect(range(13, 0))
            ->map(function (int $daysAgo): array {
                $date = today()->subDays($daysAgo);

                return [
                    'label' => $date->format('M d'),
                    'value' => DB::table('submissions')->whereDate('submitted_at', $date)->count(),
                ];
            })
            ->values()
            ->all());
    }

    /** @return list<array<string, mixed>> */
    private function attentionGroups(): array
    {
        return [
            ['title' => 'Low Pass Rate Courses (< 70%)', 'count' => DB::table('at_risk_flags')->where('status', 'OPEN')->count(), 'icon' => 'attention-01.svg', 'items' => []],
            ['title' => 'Ungraded Work (> 7 Days Overdue)', 'count' => DB::table('submissions')->leftJoin('submission_grades', 'submission_grades.submission_id', '=', 'submissions.submission_id')->whereNull('submission_grades.grade_id')->where('submissions.submitted_at', '<', now()->subDays(7))->count(), 'icon' => 'attention-02.svg', 'items' => []],
            ['title' => 'Integration & Data Alerts', 'count' => DB::table('failed_jobs')->count(), 'icon' => 'attention-03.svg', 'items' => []],
        ];
    }

    /** @return list<array<string, string>> */
    private function recentActivity(): array
    {
        return array_values(DB::table('users')
            ->latest('created_at')
            ->limit(6)
            ->get(['full_name', 'created_at'])
            ->map(fn (object $user): array => [
                'initials' => collect(explode(' ', $user->full_name))->map(fn (string $part): string => mb_substr($part, 0, 1))->take(2)->implode(''),
                'title' => 'User Provisioned · '.$user->full_name,
                'detail' => 'Account is available in Progress LMS.',
                'time' => Carbon::parse($user->created_at)->diffForHumans(),
                'category' => 'Identity',
            ])
            ->values()
            ->all());
    }
}
