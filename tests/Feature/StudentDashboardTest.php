<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function userWithStudentRole(): User
{
    $user = User::factory()->create();
    $roleId = DB::table('roles')->where('role_code', 'student')->value('role_id');

    DB::table('user_roles')->insert([
        'user_id' => $user->getKey(),
        'role_id' => $roleId,
        'granted_at' => now(),
    ]);

    return $user;
}

test('only student accounts can open the student dashboard', function () {
    $this->get(route('student.dashboard'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('student.dashboard'))
        ->assertForbidden();
});

test('student accounts receive a backend-driven dashboard payload', function () {
    $student = userWithStudentRole();

    $this->actingAs($student)
        ->get(route('student.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('student/Dashboard')
            ->where('studentDashboard.student.name', $student->name)
            ->where('studentDashboard.student.studentCode', 'Profile pending')
            ->has('studentDashboard.summary', 4)
            ->has('studentDashboard.courses', 0)
            ->has('studentDashboard.toDo', 0)
            ->has('studentDashboard.feedback', 0)
        );
});

test('student accounts are redirected from the general dashboard', function () {
    $student = userWithStudentRole();

    $this->actingAs($student)
        ->get(route('dashboard'))
        ->assertRedirect(route('student.dashboard'));
});

test('student dashboard reads enrollments and published assessments from the backend', function () {
    $studentUser = userWithStudentRole();
    $departmentId = DB::table('departments')->insertGetId([
        'department_code' => 'cs',
        'department_name' => 'Computer Science',
    ], 'department_id');
    $programId = DB::table('programs')->insertGetId([
        'department_id' => $departmentId,
        'program_code' => 'bscs',
        'program_name' => 'BSc Computer Science',
        'duration_terms' => 8,
    ], 'program_id');
    $batchId = DB::table('batches')->insertGetId([
        'program_id' => $programId,
        'intake_year' => 2026,
        'batch_code' => 'bscs2026',
    ], 'batch_id');
    $studentId = DB::table('students')->insertGetId([
        'user_id' => $studentUser->getKey(),
        'batch_id' => $batchId,
        'batch_seq' => 1,
        'student_code' => 'bscs2026-0001',
    ], 'student_id');
    $termId = DB::table('academic_terms')->insertGetId([
        'term_code' => '2026-FALL',
        'term_name' => 'Fall 2026',
        'start_date' => today()->subMonth(),
        'end_date' => today()->addMonths(3),
        'is_current' => true,
    ], 'term_id');
    $subjectId = DB::table('subjects')->insertGetId([
        'department_id' => $departmentId,
        'subject_code' => 'CS 310',
        'subject_title' => 'Data Structures and Algorithms',
        'credit_hours' => 3,
    ], 'subject_id');
    $scaleId = DB::table('grading_scales')->insertGetId([
        'scale_name' => 'Standard',
        'version_no' => 1,
    ], 'grading_scale_id');
    $classId = DB::table('classes')->insertGetId([
        'subject_id' => $subjectId,
        'term_id' => $termId,
        'grading_scale_id' => $scaleId,
        'crn_code' => 'CS-310-01',
        'section_name' => 'Section A',
        'max_capacity' => 30,
        'status' => 'ACTIVE',
    ], 'class_id');
    DB::table('enrollments')->insert([
        'class_id' => $classId,
        'student_id' => $studentId,
        'status' => 'ENROLLED',
    ]);
    $categoryId = DB::table('grade_categories')->insertGetId([
        'class_id' => $classId,
        'category_name' => 'Exams',
        'weight_percentage' => 100,
        'display_order' => 1,
    ], 'category_id');
    DB::table('assessments')->insert([
        'class_id' => $classId,
        'category_id' => $categoryId,
        'title' => 'Midterm Examination',
        'assessment_type' => 'EXAM',
        'max_marks' => 100,
        'due_at' => now()->addWeek(),
        'is_published' => true,
    ]);

    $this->actingAs($studentUser)
        ->get(route('student.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('studentDashboard.term.label', 'Fall 2026')
            ->where('studentDashboard.student.studentCode', 'bscs2026-0001')
            ->where('studentDashboard.courses.0.code', 'CS 310')
            ->where('studentDashboard.toDo.0.title', 'Midterm Examination')
            ->where('studentDashboard.summary.3.value', '1 Upcoming')
        );
});
