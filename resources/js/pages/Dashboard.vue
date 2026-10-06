<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

type Metric = { label: string; value: string; trend: string; detail: string; icon: string; critical?: boolean };
type Department = { name: string; value: number };
type Grade = { grade: string; percent: number; color: string };
type ActivityPoint = { label: string; value: number };
type AttentionGroup = { title: string; count: number; icon: string; items: Array<Record<string, string>> };
type Activity = { initials: string; title: string; detail: string; time: string; category: string };
type Action = { label: string; icon: string; primary?: boolean };
type Health = { name: string; detail: string; status: string; icon: string };

const props = defineProps<{
    dashboard: {
        institution: { name: string; scope: string; campus: string; term: string; context: string; summary: string };
        user: { name: string; role: string };
        terms: Array<{ id: number; label: string }>;
        colleges: Array<{ id: string; label: string }>;
        sync: { label: string; detail: string };
        metrics: Metric[];
        departments: Department[];
        gradeDistribution: Grade[];
        gradedEnrollments: number;
        passRate: number;
        activityTrend: ActivityPoint[];
        attentionGroups: AttentionGroup[];
        recentActivity: Activity[];
        adminActions: Action[];
        systemHealth: Health[];
    };
}>();

const asset = (name: string) => `/images/dashboard/${name}`;
const navItems = [
    ['Dashboard', 'sidebar-02.svg'], ['Users', 'sidebar-03.svg'], ['Academics', 'sidebar-04.svg'],
    ['Classes', 'sidebar-05.svg'], ['Reports', 'sidebar-06.svg'], ['Activity Log', 'sidebar-07.svg'], ['Settings', 'sidebar-08.svg'],
];
const maxDepartment = computed(() => Math.max(1, ...props.dashboard.departments.map((item) => item.value)));
const donut = computed(() => {
    let offset = 0;
    const parts = props.dashboard.gradeDistribution.map((item) => {
        const start = offset;
        offset += item.percent;
        return `${item.color} ${start}% ${offset}%`;
    });
    if (offset < 100) parts.push(`#e9edf4 ${offset}% 100%`);
    return `conic-gradient(${parts.join(',')})`;
});
const chartPoints = computed(() => {
    const values = props.dashboard.activityTrend.map((item) => item.value);
    const max = Math.max(1, ...values);
    return values.map((value, index) => `${(index / Math.max(1, values.length - 1)) * 100},${90 - (value / max) * 75}`).join(' ');
});
</script>

<template>
    <Head title="Admin Dashboard" />
    <div class="min-h-screen bg-[#f8f9ff] text-[#0b1c30] [font-family:Inter,ui-sans-serif,system-ui,sans-serif] xl:flex">
        <aside class="flex w-full shrink-0 flex-col justify-between bg-[#213145] text-[#d8e3fb] xl:sticky xl:top-0 xl:h-screen xl:w-64">
            <div>
                <div class="flex h-16 items-center gap-3 px-6">
                    <div class="grid size-9 place-items-center rounded-lg bg-[#0f766e]"><img :src="asset('sidebar-01.svg')" alt="" /></div>
                    <div><p class="font-semibold text-[#eaf1ff]">{{ dashboard.institution.name }}</p><p class="text-[11px] font-medium tracking-[.05em] text-[#bcc7de] uppercase">{{ dashboard.institution.scope }}</p></div>
                </div>
                <div class="px-4 py-2">
                    <p class="px-2 pb-1 text-[11px] font-medium tracking-[.05em] text-[#bcc7de] uppercase">Administrative</p>
                    <nav class="grid gap-1">
                        <button v-for="([label, icon], index) in navItems" :key="label" class="flex items-center gap-3 rounded-lg p-2 text-left text-[13px] font-medium" :class="index === 0 ? 'bg-[#0f766e] text-[#a3faef]' : 'hover:bg-white/5'">
                            <img :src="asset(icon)" alt="" class="size-[18px]" />{{ label }}
                        </button>
                    </nav>
                </div>
            </div>
            <div class="grid gap-2 p-4">
                <button class="flex items-center justify-between rounded-lg p-2 text-[13px]"><span class="flex items-center gap-2"><img :src="asset('sidebar-09.svg')" alt="" class="size-4" />Support &amp; Docs</span><img :src="asset('sidebar-10.svg')" alt="" class="size-3" /></button>
                <div class="flex items-center gap-3 rounded-lg p-2"><div class="grid size-9 place-items-center rounded-full bg-[#0f766e]"><img :src="asset('sidebar-11.svg')" alt="" class="size-3" /></div><div class="min-w-0 flex-1"><p class="truncate text-[13px] font-medium text-[#eaf1ff]">{{ dashboard.user.name }}</p><p class="truncate text-[11px] text-[#bcc7de]">{{ dashboard.user.role }}</p></div><img :src="asset('sidebar-12.svg')" alt="" class="h-3 w-1" /></div>
            </div>
        </aside>

        <div class="min-w-0 flex-1">
            <header class="sticky top-0 z-20 flex h-16 items-center justify-between gap-6 bg-[#f8f9ff]/90 px-6 shadow-[0_1px_8px_rgba(0,0,0,.04)] backdrop-blur-xl">
                <div class="flex min-w-0 max-w-xl flex-1 items-center gap-4">
                    <label class="flex h-10 min-w-0 flex-1 items-center gap-3 rounded-lg bg-white px-3 shadow-sm"><img :src="asset('topbar-01.svg')" alt="" class="size-3.5" /><input class="min-w-0 flex-1 bg-transparent text-sm outline-none" placeholder="Search anything in Progress LMS..." /></label>
                    <button class="hidden h-10 items-center gap-2 rounded-lg bg-white px-3 text-[13px] font-medium shadow-sm md:flex"><img :src="asset('topbar-02.svg')" alt="" class="size-4" />{{ dashboard.institution.campus }}<img :src="asset('topbar-03.svg')" alt="" class="h-3 w-2" /></button>
                </div>
                <div class="flex items-center gap-4"><span class="hidden items-center gap-2 rounded-full bg-[#eff4ff] px-3 py-1.5 text-xs font-medium text-[#005e3f] sm:flex"><i class="size-2 rounded-full bg-[#007952]"></i>All Systems Operational</span><button class="relative grid size-10 place-items-center rounded-lg bg-white shadow-sm"><img :src="asset('topbar-04.svg')" alt="Notifications" class="size-4" /><i class="absolute top-2 right-2 size-2 rounded-full bg-[#ba1a1a] ring-2 ring-white"></i></button><div class="grid size-8 place-items-center rounded-full bg-[#005c55]"><img :src="asset('topbar-05.svg')" alt="" class="size-3" /></div></div>
            </header>

            <main class="grid gap-6 p-6">
                <section class="rounded-lg bg-white p-6 shadow-sm">
                    <div class="flex flex-col justify-between gap-5 lg:flex-row">
                        <div><div class="flex flex-wrap items-center gap-3"><h1 class="text-[28px] leading-9 font-bold tracking-[-.025em]">Admin<br />Dashboard</h1><span class="flex items-center gap-2 rounded-full bg-[#eff4ff] px-3 py-1 text-[11px] font-medium tracking-[.05em] text-[#005c55] uppercase"><i class="size-1.5 rounded-full bg-[#005c55]"></i>{{ dashboard.institution.context }} · {{ dashboard.institution.term }}</span></div><p class="mt-1 max-w-lg text-sm leading-5 text-[#545f73]">{{ dashboard.institution.summary }}</p></div>
                        <div class="grid content-start gap-3"><span class="rounded-lg bg-[#eff4ff] px-3 py-1.5 text-[11px]"><b>{{ dashboard.sync.label }}:</b>&nbsp; {{ dashboard.sync.detail }}</span><div class="flex gap-4"><button class="flex h-10 items-center gap-2 rounded-lg bg-[#eff4ff] px-4 text-[13px] font-medium"><img :src="asset('header-01.svg')" alt="" class="size-3" />Generate System Report</button><button class="grid size-10 place-items-center rounded-lg bg-[#eff4ff]"><img :src="asset('header-02.svg')" alt="Settings" class="size-4" /></button></div></div>
                    </div>
                    <div class="mt-4 flex flex-col justify-between gap-3 border-t border-[#f3f5f9] pt-4 md:flex-row"><div class="flex gap-2"><select class="h-9 rounded-lg bg-[#eff4ff] px-3 text-[13px] font-medium"><option v-for="term in dashboard.terms" :key="term.id">{{ term.label }}</option><option v-if="!dashboard.terms.length">No active term</option></select><select class="h-9 rounded-lg bg-[#eff4ff] px-3 text-[13px] font-medium"><option v-for="college in dashboard.colleges" :key="college.id">{{ college.label }}</option></select></div><label class="flex h-9 w-full items-center gap-3 rounded-lg bg-[#eff4ff] px-3 md:w-96"><img :src="asset('header-04.svg')" alt="" class="size-3.5" /><input class="min-w-0 flex-1 bg-transparent text-[13px] outline-none" placeholder="Search students, faculty, CRNs... (Press /)" /><kbd class="rounded bg-[#e5eeff] px-1.5 py-0.5 text-[10px]">Ctrl K</kbd></label></div>
                </section>

                <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6">
                    <article v-for="metric in dashboard.metrics" :key="metric.label" class="flex min-h-[170px] flex-col justify-between rounded-lg bg-white p-4 shadow-sm"><div class="flex justify-between"><p class="text-xs font-medium tracking-[.04em] text-[#545f73] uppercase">{{ metric.label }}</p><img :src="asset(metric.icon)" alt="" class="size-4" /></div><div class="flex items-end gap-1"><strong class="text-[28px] leading-9 tracking-[-.02em]">{{ metric.value }}</strong><span class="mb-1 rounded px-1.5 py-0.5 text-xs" :class="metric.critical ? 'bg-[#ffdad6]/40 text-[#ba1a1a]' : 'bg-[#eff4ff] text-[#006b5f]'">{{ metric.trend }}</span></div><div class="flex items-end justify-between gap-2"><span class="text-[11px] leading-[14px] text-[#545f73]">{{ metric.detail }}</span><svg viewBox="0 0 40 24" class="h-6 w-10" fill="none"><path d="M1 20 C9 21 12 14 20 15 S29 7 39 8" :stroke="metric.critical ? '#d33' : '#0f766e'" stroke-width="1.5" /></svg></div></article>
                </section>

                <section class="grid gap-6 lg:grid-cols-3">
                    <article class="rounded-lg bg-white p-5 shadow-sm"><h2 class="font-semibold">Enrollment by Department</h2><p class="text-xs text-[#545f73]">Top programs ranked by headcount</p><div class="mt-6 grid gap-4"><div v-for="department in dashboard.departments" :key="department.name"><div class="mb-1 flex justify-between text-xs"><b>{{ department.name }}</b><span>{{ department.value }}</span></div><div class="h-2 rounded-full bg-[#eff4ff]"><div class="h-full rounded-full bg-[#006b5f]" :style="{ width: `${(department.value / maxDepartment) * 100}%` }"></div></div></div><p v-if="!dashboard.departments.length" class="py-16 text-center text-sm text-[#545f73]">No department enrollment data yet.</p></div><div class="mt-6 flex justify-between text-xs font-medium text-[#006b5f]"><span>View All Departments</span><span>Total: {{ dashboard.metrics[0]?.value }} Students</span></div></article>
                    <article class="rounded-lg bg-white p-5 shadow-sm"><h2 class="font-semibold">Grade Distribution</h2><p class="text-xs text-[#545f73]">Across all active courses</p><div class="mx-auto mt-8 grid size-40 place-items-center rounded-full" :style="{ background: donut }"><div class="grid size-24 place-items-center rounded-full bg-white text-center"><div><b class="text-2xl">{{ dashboard.passRate }}%</b><p class="text-[11px] text-[#545f73]">PASS RATE</p></div></div></div><div class="mt-8 grid grid-cols-5 gap-1"><div v-for="grade in dashboard.gradeDistribution" :key="grade.grade" class="rounded bg-[#eff4ff] p-2 text-center text-xs"><b><i class="mr-1 inline-block size-2 rounded-full" :style="{ background: grade.color }"></i>{{ grade.grade }}</b><p class="text-[#545f73]">{{ grade.percent }}%</p></div></div><p class="mt-5 text-center text-xs text-[#545f73]">{{ dashboard.gradedEnrollments }} graded enrollments · Verified marks</p></article>
                    <article class="rounded-lg bg-white p-5 shadow-sm"><div class="flex justify-between"><div><h2 class="font-semibold">Platform Activity</h2><p class="text-xs text-[#545f73]">Daily academic events</p></div><span class="h-fit rounded bg-[#eff4ff] px-2 py-1 text-[10px] font-semibold">14D</span></div><strong class="mt-4 block text-2xl">{{ Math.max(0, ...dashboard.activityTrend.map((item) => item.value)) }} <small class="text-xs font-normal text-[#545f73]">peak events</small></strong><svg viewBox="0 0 100 100" preserveAspectRatio="none" class="mt-4 h-44 w-full"><defs><linearGradient id="activity-fill" x1="0" x2="0" y1="0" y2="1"><stop stop-color="#0f766e" stop-opacity=".22"/><stop offset="1" stop-color="#0f766e" stop-opacity="0"/></linearGradient></defs><polyline :points="`0,100 ${chartPoints} 100,100`" fill="url(#activity-fill)" stroke="none"/><polyline :points="chartPoints" fill="none" stroke="#0f766e" stroke-width="1.5" vector-effect="non-scaling-stroke"/></svg><div class="flex justify-between text-[10px] text-[#545f73]"><span>{{ dashboard.activityTrend[0]?.label }}</span><span>{{ dashboard.activityTrend[6]?.label }}</span><span>Today</span></div><p class="mt-8 text-xs text-[#006b5f]">● Live events calculated from backend records</p></article>
                </section>

                <section class="rounded-lg bg-white p-5 shadow-sm"><div class="flex flex-col justify-between gap-4 sm:flex-row"><div class="flex items-center gap-3"><span class="grid size-8 place-items-center rounded-lg bg-[#ffdad6]/50 text-[#ba1a1a]">△</span><div><h2 class="font-semibold">Needs Attention <span class="ml-2 rounded-full bg-[#ffdad6]/50 px-2 py-1 text-xs text-[#ba1a1a]">{{ dashboard.attentionGroups.reduce((sum, group) => sum + group.count, 0) }} Urgent Items</span></h2><p class="text-xs text-[#545f73]">High-priority alerts requiring academic dean or LMS provost intervention</p></div></div><div class="flex gap-2"><button class="rounded-lg bg-[#eff4ff] px-4 py-2 text-xs font-medium">Mark All Reviewed</button><button class="rounded-lg bg-[#006b5f] px-4 py-2 text-xs font-medium text-white">Bulk Resolve &amp; Notify</button></div></div><div class="mt-6 grid gap-4 lg:grid-cols-3"><div v-for="group in dashboard.attentionGroups" :key="group.title" class="min-h-[220px] rounded-lg bg-[#f4f7ff] p-4"><div class="flex items-center justify-between text-xs"><b class="flex items-center gap-2"><img :src="asset(group.icon)" alt="" class="size-4" />{{ group.title }}</b><span>{{ group.count }} Alerts</span></div><div v-if="group.items.length" class="mt-4 grid gap-2"></div><div v-else class="grid h-36 place-items-center text-center text-xs text-[#545f73]">No unresolved items in this category.</div><button class="text-xs font-medium text-[#006b5f]">View diagnostic details →</button></div></div></section>

                <section class="grid gap-6 lg:grid-cols-[2fr_1fr]"><article class="min-h-[560px] rounded-lg bg-white p-5 shadow-sm"><div class="flex justify-between"><div><h2 class="font-semibold">Recent Activity (Audit Log)</h2><p class="text-xs text-[#545f73]">Real-time trace of administrative, instructional, and system actions</p></div><span class="rounded-lg bg-[#eff4ff] px-3 py-2 text-xs">All &nbsp; Academic &nbsp; Security &nbsp; Integrations</span></div><div class="mt-8 grid gap-6"><div v-for="activity in dashboard.recentActivity" :key="activity.title" class="flex gap-3"><span class="grid size-8 shrink-0 place-items-center rounded-full bg-[#dce8ff] text-xs">{{ activity.initials }}</span><div class="min-w-0 flex-1"><div class="flex flex-wrap justify-between gap-2"><b class="text-[13px]">{{ activity.title }}</b><span class="text-[11px] text-[#545f73]">{{ activity.time }} · {{ activity.category }}</span></div><p class="text-xs text-[#545f73]">{{ activity.detail }}</p></div></div><p v-if="!dashboard.recentActivity.length" class="py-20 text-center text-sm text-[#545f73]">No recent administrative activity.</p></div><button class="mt-10 text-xs font-medium text-[#006b5f]">View Full Audit Log in Activity Center →</button></article><div class="grid content-start gap-6"><article class="rounded-lg bg-white p-5 shadow-sm"><h2 class="font-semibold">Administrative Actions</h2><p class="text-xs text-[#545f73]">Frequent operations &amp; roster tools</p><div class="mt-4 grid gap-2"><button v-for="action in dashboard.adminActions" :key="action.label" class="flex items-center gap-3 rounded-lg px-3 py-3 text-left text-[13px] font-medium" :class="action.primary ? 'bg-[#006b5f] text-white' : 'bg-[#eff4ff]'" ><img :src="asset(action.icon)" alt="" class="size-4" />{{ action.label }}<span class="ml-auto">›</span></button></div></article><article class="rounded-lg bg-white p-5 shadow-sm"><div class="flex justify-between"><h2 class="font-semibold">Connector &amp; System Health</h2><i class="size-2 rounded-full bg-[#007952]"></i></div><div class="mt-5 grid gap-3"><div v-for="health in dashboard.systemHealth" :key="health.name" class="flex items-center gap-3 rounded-lg bg-[#eff4ff] p-3"><img :src="asset(health.icon)" alt="" class="size-4" /><div class="min-w-0 flex-1"><b class="text-xs">{{ health.name }}</b><p class="text-[11px] text-[#545f73]">{{ health.detail }}</p></div><span class="rounded-full bg-[#dff5ed] px-2 py-1 text-[10px] text-[#006b5f]">{{ health.status }}</span></div></div><div class="mt-5 flex justify-between text-[11px] text-[#545f73]"><span>Server Region: local</span><button class="text-[#006b5f]">Diagnostics →</button></div></article></div></section>
            </main>
        </div>
    </div>
</template>
