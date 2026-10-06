<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

type Summary = {
    label: string;
    value: string;
    icon: string;
    warning?: boolean;
};
type Course = {
    class_id: number;
    code: string;
    title: string;
    section: string;
    term: string;
    instructor: string;
    credit_hours: number;
    standing: number | null;
    letter: string;
    assessments: number;
    discussions: number;
    files: number;
    theme: number;
};
type ToDo = {
    id: number;
    title: string;
    course: string;
    points: string;
    status: string;
    due: string;
};
type Feedback = {
    title: string;
    course: string;
    score: string;
    feedback: string;
    time: string;
};

defineProps<{
    studentDashboard: {
        student: {
            name: string;
            studentCode: string;
            program: string;
            intakeYear: number | null;
        };
        term: { label: string; code: string };
        summary: Summary[];
        courses: Course[];
        toDo: ToDo[];
        feedback: Feedback[];
        examAlert: { title: string; detail: string } | null;
        quickLinks: Array<{ label: string; icon: string }>;
    };
}>();

const asset = (name: string) => `/images/student/${name}`;
const courseThemes = [
    ['from-[#0f766e] to-[#134e4a]', '#0f766e', 'course-05.svg'],
    ['from-[#4338ca] to-[#1e1b4b]', '#4f46e5', 'course-13.svg'],
    ['from-[#059669] to-[#064e3b]', '#059669', 'course-14.svg'],
    ['from-[#d97706] to-[#7c2d12]', '#d97706', 'course-15.svg'],
    ['from-[#be123c] to-[#4c0519]', '#be123c', 'course-16.svg'],
    ['from-[#0e7490] to-[#164e63]', '#0e7490', 'course-17.svg'],
];
const railItems = [
    ['ACCOUNT', 'rail-01.svg'],
    ['DASHBOARD', 'rail-02.svg'],
    ['COURSES', 'rail-03.svg'],
    ['CALENDAR', 'rail-04.svg'],
    ['INBOX', 'rail-05.svg'],
    ['HISTORY', 'rail-06.svg'],
    ['HELP', 'rail-07.svg'],
];
const statusClass = (status: string) =>
    ({
        Submitted: 'bg-[#e4f8f2] text-[#00795c] border-[#a6e6d7]',
        Late: 'bg-[#fff7df] text-[#a65100] border-[#f1cf70]',
        Missing: 'bg-[#fff0f1] text-[#c32136] border-[#efb8c0]',
        Excused: 'bg-[#eef2f7] text-[#526174] border-[#d8e0ea]',
        Due: 'bg-[#eaf0ff] text-[#315895] border-[#cad8f5]',
    })[status] ?? 'bg-[#eef2f7] text-[#526174] border-[#d8e0ea]';
</script>

<template>
    <Head title="Student Dashboard" />
    <div
        class="min-h-screen bg-[#f7f8ff] [font-family:Inter,ui-sans-serif,system-ui,sans-serif] text-[#102039] lg:pl-[72px]"
    >
        <aside
            class="z-30 flex bg-[#202c3d] text-[#dbe5f5] lg:fixed lg:inset-y-0 lg:left-0 lg:w-[72px] lg:flex-col lg:justify-between"
        >
            <div class="flex min-w-0 lg:block">
                <div class="grid h-16 w-[72px] shrink-0 place-items-center">
                    <img
                        :src="asset('brand.png')"
                        alt="Veritas"
                        class="size-9 rounded-lg object-cover"
                    />
                </div>
                <nav
                    class="flex min-w-0 flex-1 overflow-x-auto px-1 lg:grid lg:overflow-visible lg:px-2"
                >
                    <button
                        v-for="([label, icon], index) in railItems"
                        :key="label"
                        class="relative grid h-14 min-w-14 place-items-center rounded-lg text-[9px] tracking-[-.01em] lg:w-14"
                        :class="
                            index === 1
                                ? 'bg-[#0f887d] text-white'
                                : 'hover:bg-white/5'
                        "
                    >
                        <span class="grid place-items-center gap-1">
                            <img :src="asset(icon)" alt="" class="h-4 w-5" />
                            {{ label }}
                        </span>
                        <span
                            v-if="label === 'INBOX'"
                            class="absolute top-1.5 right-1.5 rounded-full bg-[#068c7c] px-1 text-[9px]"
                            >3</span
                        >
                    </button>
                </nav>
            </div>
            <div class="hidden place-items-center pb-3 lg:grid">
                <img
                    :src="asset('student.jpg')"
                    alt="Student profile"
                    class="size-8 rounded-full border-2 border-[#8ba0b9] object-cover"
                />
                <span class="mt-1 text-[10px]">Student</span>
            </div>
        </aside>

        <header
            class="flex min-h-14 flex-wrap items-center justify-between gap-3 border-b border-[#dce3ee] bg-white px-6 py-2 lg:h-14 lg:py-0"
        >
            <div class="flex min-w-0 flex-1 items-center gap-4">
                <span
                    class="hidden rounded-full bg-[#e5edff] px-3 py-1 text-xs font-medium text-[#516078] sm:block"
                    >{{ studentDashboard.term.code }}</span
                >
                <label
                    class="flex h-8 w-full max-w-96 items-center gap-3 rounded-lg bg-[#f0f4fc] px-3 ring-1 ring-[#d9e1ef]"
                >
                    <img
                        :src="asset('topbar-01.svg')"
                        alt=""
                        class="size-3.5"
                    />
                    <input
                        class="min-w-0 flex-1 bg-transparent text-xs outline-none"
                        placeholder="Search courses, assignments, or people..."
                    />
                </label>
            </div>
            <div class="flex items-center gap-4 text-xs">
                <span class="hidden items-center gap-1.5 md:flex">
                    <img
                        :src="asset('topbar-02.svg')"
                        alt=""
                        class="size-4"
                    />Services
                </span>
                <button class="relative p-1">
                    <img
                        :src="asset('topbar-03.svg')"
                        alt="Notifications"
                        class="size-4"
                    />
                    <i
                        class="absolute top-0 right-0 size-2 rounded-full bg-[#b42318] ring-2 ring-white"
                    ></i>
                </button>
                <span class="h-5 w-px bg-[#dce3ee]"></span>
                <img
                    :src="asset('profile.jpg')"
                    alt=""
                    class="size-8 rounded-full object-cover"
                />
                <span class="hidden leading-tight sm:block">
                    <strong class="block font-semibold">{{
                        studentDashboard.student.name
                    }}</strong>
                    <span class="text-[11px] text-[#516078]">{{
                        studentDashboard.student.studentCode
                    }}</span>
                </span>
            </div>
        </header>

        <main class="mx-auto max-w-[1208px] p-6 lg:p-8">
            <section
                class="flex flex-col justify-between gap-5 border-b border-[#dce3ee] pb-6 xl:flex-row"
            >
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-[28px] font-bold tracking-[-.025em]">
                            Dashboard
                        </h1>
                        <span
                            class="rounded-full bg-[#ddf1ee] px-3 py-1 text-[11px] font-medium text-[#00655d]"
                            >Active Term</span
                        >
                    </div>
                    <p class="mt-1 text-sm leading-5 text-[#526174]">
                        Welcome back, {{ studentDashboard.student.name }} ·
                        {{ studentDashboard.student.program }}
                    </p>
                </div>
                <div class="flex flex-wrap items-start gap-5">
                    <div>
                        <div
                            class="flex rounded-lg bg-[#e6eeff] p-1 ring-1 ring-[#cad7ec]"
                        >
                            <button
                                class="flex h-7 items-center gap-1.5 rounded-md bg-white px-3 text-xs font-medium shadow-sm"
                            >
                                <img
                                    :src="asset('header-01.svg')"
                                    alt=""
                                    class="size-3"
                                />Card view
                            </button>
                            <button
                                class="flex h-7 items-center gap-1.5 px-3 text-xs text-[#56647a]"
                            >
                                <img
                                    :src="asset('header-02.svg')"
                                    alt=""
                                    class="size-3"
                                />List view
                            </button>
                            <button
                                class="hidden h-7 items-center gap-1.5 px-3 text-xs text-[#56647a] sm:flex"
                            >
                                <img
                                    :src="asset('header-03.svg')"
                                    alt=""
                                    class="size-3"
                                />Recent activity
                            </button>
                        </div>
                        <div class="mt-4 flex items-center gap-4">
                            <button
                                class="flex h-9 items-center gap-2 rounded-lg border border-[#d2dbe8] px-4 text-xs font-medium"
                            >
                                <img
                                    :src="asset('header-04.svg')"
                                    alt=""
                                    class="size-3"
                                />Join with Code
                            </button>
                            <img
                                :src="asset('header-05.svg')"
                                alt="Filters"
                                class="size-4"
                            />
                        </div>
                    </div>
                    <button
                        class="flex h-9 items-center gap-2 rounded-lg border border-[#d2dbe8] bg-white px-4 text-xs font-medium"
                    >
                        <img
                            :src="asset('header-06.svg')"
                            alt=""
                            class="size-3"
                        />{{ studentDashboard.term.label }}
                        <img
                            :src="asset('header-07.svg')"
                            alt=""
                            class="h-2 w-3"
                        />
                    </button>
                </div>
            </section>

            <div class="mt-6 grid gap-8 xl:grid-cols-[minmax(0,802px)_310px]">
                <section class="min-w-0">
                    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                        <article
                            v-for="item in studentDashboard.summary"
                            :key="item.label"
                            class="flex min-h-[68px] items-center justify-between rounded-lg border border-[#dce3e7] bg-white p-4"
                        >
                            <div>
                                <p
                                    class="text-[11px] font-medium tracking-[.05em] text-[#526174] uppercase"
                                >
                                    {{ item.label }}
                                </p>
                                <strong
                                    class="mt-0.5 block text-lg"
                                    :class="
                                        item.warning ? 'text-[#b45309]' : ''
                                    "
                                    >{{ item.value }}</strong
                                >
                            </div>
                            <span
                                class="grid size-8 place-items-center rounded-full bg-[#e5f1ef]"
                            >
                                <img
                                    :src="asset(item.icon)"
                                    alt=""
                                    class="size-4"
                                />
                            </span>
                        </article>
                    </div>

                    <div
                        v-if="studentDashboard.courses.length"
                        class="mt-6 grid gap-5 md:grid-cols-2 lg:grid-cols-3"
                    >
                        <article
                            v-for="course in studentDashboard.courses"
                            :key="course.class_id"
                            class="overflow-hidden rounded-lg border border-[#d5dde8] bg-white"
                        >
                            <div
                                class="relative flex h-28 flex-col justify-between overflow-hidden bg-gradient-to-br p-3 text-white"
                                :class="courseThemes[course.theme][0]"
                            >
                                <img
                                    :src="asset(courseThemes[course.theme][2])"
                                    alt=""
                                    class="absolute -right-4 -bottom-6 size-32 opacity-25"
                                />
                                <div class="relative flex justify-between">
                                    <span
                                        class="rounded border border-white/25 bg-black/25 px-2 py-0.5 text-[11px]"
                                        >{{ course.code }}</span
                                    >
                                    <span class="text-lg leading-none">⋮</span>
                                </div>
                                <div
                                    class="relative flex items-end justify-between"
                                >
                                    <span
                                        class="text-[11px] tracking-[.05em] text-white/80 uppercase"
                                        >{{ course.term }}</span
                                    >
                                    <span>★</span>
                                </div>
                            </div>
                            <div class="p-4">
                                <h2 class="truncate text-lg font-semibold">
                                    {{ course.title }}
                                </h2>
                                <p
                                    class="mt-0.5 truncate text-[11px] text-[#58677d]"
                                >
                                    {{ course.section }}
                                </p>
                                <p
                                    class="mt-1.5 truncate text-xs text-[#405067]"
                                >
                                    ♙ {{ course.instructor }}
                                </p>
                                <div
                                    class="mt-4 border-t border-[#edf0f5] pt-3"
                                >
                                    <div
                                        class="flex items-center justify-between gap-2"
                                    >
                                        <span
                                            class="text-[11px] font-medium tracking-[.04em] text-[#58677d] uppercase"
                                            >Current standing</span
                                        >
                                        <strong
                                            class="rounded border border-[#a6e6d7] bg-[#e5faf4] px-2 py-1 text-xs text-[#00795c]"
                                            >{{
                                                course.standing === null
                                                    ? 'Not graded'
                                                    : `${course.standing}% (${course.letter})`
                                            }}</strong
                                        >
                                    </div>
                                    <div
                                        class="mt-2 h-1.5 overflow-hidden rounded-full bg-[#e8eef9]"
                                    >
                                        <i
                                            class="block h-full rounded-full"
                                            :style="{
                                                width: `${course.standing ?? 0}%`,
                                                backgroundColor:
                                                    courseThemes[
                                                        course.theme
                                                    ][1],
                                            }"
                                        ></i>
                                    </div>
                                </div>
                            </div>
                            <footer
                                class="flex items-center justify-between border-t border-[#dce3ee] bg-[#f5f7fc] px-4 py-2.5 text-[11px]"
                            >
                                <span
                                    class="flex items-center gap-3 text-[#53637a]"
                                >
                                    <span>▣ {{ course.assessments }}</span>
                                    <span>▰ {{ course.discussions }}</span>
                                    <span>▱ {{ course.files }}</span>
                                </span>
                                <strong class="font-medium text-[#00665d]"
                                    >Enter›</strong
                                >
                            </footer>
                        </article>
                    </div>
                    <div
                        v-else
                        class="mt-6 grid min-h-80 place-items-center rounded-lg border border-dashed border-[#bdc9d9] bg-white p-10 text-center"
                    >
                        <div>
                            <div
                                class="mx-auto grid size-14 place-items-center rounded-full bg-[#e5f1ef] text-2xl"
                            >
                                ▤
                            </div>
                            <h2 class="mt-4 text-lg font-semibold">
                                No active course enrollments
                            </h2>
                            <p class="mt-1 max-w-sm text-sm text-[#5b697e]">
                                Courses will appear here as soon as this student
                                is enrolled in an active class for the selected
                                term.
                            </p>
                        </div>
                    </div>

                    <article
                        class="mt-8 flex flex-col justify-between gap-5 rounded-lg border border-[#dce3e7] bg-white p-5 md:flex-row md:items-center"
                    >
                        <div class="flex gap-4">
                            <span
                                class="grid size-12 shrink-0 place-items-center rounded-lg bg-[#e5f3f1]"
                                ><img
                                    :src="asset('course-18.svg')"
                                    alt=""
                                    class="size-6"
                            /></span>
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="text-[11px] font-semibold tracking-[.05em] text-[#00665d] uppercase"
                                        >Library research support</span
                                    >
                                    <i
                                        class="size-1.5 rounded-full bg-[#10b981]"
                                    ></i>
                                    <span class="text-[11px] text-[#59677d]"
                                        >Virtual Liaison On Duty</span
                                    >
                                </div>
                                <h3 class="mt-1 font-semibold">
                                    Need journal access or IEEE/PubMed
                                    citations?
                                </h3>
                                <p
                                    class="mt-0.5 max-w-md text-xs text-[#59677d]"
                                >
                                    Connect with the university science
                                    librarian for research databases and
                                    citation support.
                                </p>
                            </div>
                        </div>
                        <div class="flex shrink-0 gap-3">
                            <button
                                class="rounded-lg border border-[#d2dbe8] px-4 py-2 text-xs font-medium"
                            >
                                Reserve Study Carrel
                            </button>
                            <button
                                class="rounded-lg bg-[#0f8177] px-4 py-2 text-xs font-medium text-white"
                            >
                                Chat with Librarian
                            </button>
                        </div>
                    </article>
                </section>

                <aside class="grid content-start gap-6">
                    <article
                        class="rounded-lg border border-[#c9d7f0] bg-[#e9effd] p-4"
                    >
                        <div class="flex gap-3">
                            <img
                                :src="asset('widget-01.svg')"
                                alt=""
                                class="mt-0.5 size-4"
                            />
                            <div>
                                <h2 class="text-xs font-semibold">
                                    {{
                                        studentDashboard.examAlert?.title ??
                                        'No upcoming examinations'
                                    }}
                                </h2>
                                <p
                                    class="mt-1 text-xs leading-5 text-[#48566a]"
                                >
                                    {{
                                        studentDashboard.examAlert?.detail ??
                                        'New exam dates will appear here when published.'
                                    }}
                                </p>
                                <p
                                    class="mt-2 text-xs font-semibold text-[#00665d]"
                                >
                                    View Exam Schedules →
                                </p>
                            </div>
                        </div>
                    </article>

                    <article
                        class="rounded-lg border border-[#dce3e7] bg-white p-4"
                    >
                        <header
                            class="flex items-center justify-between border-b border-[#edf0f5] pb-3"
                        >
                            <div class="flex items-center gap-2">
                                <h2 class="text-lg font-bold">To Do</h2>
                                <span
                                    class="rounded-full bg-[#e6ecf8] px-2 py-0.5 text-[11px] text-[#526174]"
                                    >{{ studentDashboard.toDo.length }}</span
                                >
                            </div>
                            <span class="text-[11px] text-[#526174]"
                                >By Date☷</span
                            >
                        </header>
                        <div
                            v-if="studentDashboard.toDo.length"
                            class="divide-y divide-[#edf0f5]"
                        >
                            <div
                                v-for="item in studentDashboard.toDo.slice(
                                    0,
                                    4,
                                )"
                                :key="item.id"
                                class="py-3"
                            >
                                <div class="flex gap-2">
                                    <span class="mt-0.5 text-[#0f8177]">◉</span>
                                    <div class="min-w-0 flex-1">
                                        <h3
                                            class="truncate text-xs font-semibold"
                                        >
                                            {{ item.title }}
                                        </h3>
                                        <p
                                            class="mt-1 text-[11px] text-[#536178]"
                                        >
                                            {{ item.course }}　•　{{
                                                item.points
                                            }}
                                        </p>
                                        <div
                                            class="mt-2 flex flex-wrap items-center gap-2"
                                        >
                                            <span
                                                class="rounded-full border px-2 py-0.5 text-[10px]"
                                                :class="
                                                    statusClass(item.status)
                                                "
                                                >● {{ item.status }}</span
                                            >
                                            <span
                                                class="text-[10px] text-[#536178]"
                                                >{{ item.due }}</span
                                            >
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p
                            v-else
                            class="py-10 text-center text-xs text-[#66758a]"
                        >
                            No published tasks are waiting.
                        </p>
                    </article>

                    <article
                        class="rounded-lg border border-[#dce3e7] bg-white p-4"
                    >
                        <header class="flex items-center justify-between pb-3">
                            <h2 class="text-lg font-bold">Recent Feedback</h2>
                            <span class="text-[11px] font-medium text-[#00665d]"
                                >View Grades</span
                            >
                        </header>
                        <div
                            v-if="studentDashboard.feedback.length"
                            class="grid gap-4"
                        >
                            <div
                                v-for="item in studentDashboard.feedback"
                                :key="`${item.course}-${item.title}`"
                                class="rounded-lg border border-[#e1e6ef] bg-[#f8f9fc] p-3"
                            >
                                <div class="flex justify-between gap-2">
                                    <h3 class="truncate text-xs font-semibold">
                                        {{ item.title }}
                                    </h3>
                                    <span
                                        class="shrink-0 rounded border border-[#a6e6d7] bg-[#e7faf5] px-2 py-1 text-[10px] font-semibold text-[#00795c]"
                                        >✓ {{ item.score }}</span
                                    >
                                </div>
                                <p class="mt-2 text-[10px] text-[#59677d]">
                                    {{ item.course }}
                                </p>
                                <blockquote
                                    class="mt-3 rounded border border-[#e1e6ef] bg-white p-2 text-xs leading-5 text-[#39485e] italic"
                                >
                                    “{{ item.feedback }}”
                                </blockquote>
                                <p class="mt-2 text-[10px] text-[#59677d]">
                                    {{ item.time }}
                                    <span
                                        class="float-right font-medium text-[#00665d]"
                                        >Full Rubric</span
                                    >
                                </p>
                            </div>
                        </div>
                        <p
                            v-else
                            class="py-10 text-center text-xs text-[#66758a]"
                        >
                            Published feedback will appear here.
                        </p>
                    </article>

                    <nav
                        class="rounded-lg border border-[#dce3e7] bg-white p-4"
                    >
                        <h2
                            class="text-[11px] font-semibold tracking-[.06em] text-[#59677d] uppercase"
                        >
                            Campus quick links
                        </h2>
                        <a
                            v-for="link in studentDashboard.quickLinks"
                            :key="link.label"
                            href="#"
                            class="flex items-center justify-between py-2 text-xs"
                        >
                            <span class="flex items-center gap-2"
                                ><img
                                    :src="asset(link.icon)"
                                    alt=""
                                    class="size-4"
                                />{{ link.label }}</span
                            >
                            <span>↗</span>
                        </a>
                    </nav>
                </aside>
            </div>
        </main>
    </div>
</template>
