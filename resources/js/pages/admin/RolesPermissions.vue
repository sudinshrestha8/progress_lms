<script setup lang="ts">
import {
    destroy,
    store,
    update,
} from '@/actions/App/Http/Controllers/Admin/RolePermissionController';
import { dashboard } from '@/routes';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

type Role = {
    role_id: number;
    role_code: string;
    role_name: string;
    description: string | null;
    is_system: boolean;
    users_count: number;
    permissions: number[];
};
type Permission = {
    permission_id: number;
    permission_code: string;
    module: string;
    description: string;
};

const props = defineProps<{
    accessControl: {
        user: { name: string; role: string };
        roles: Role[];
        permissions: Permission[];
        summary: {
            roles: number;
            customRoles: number;
            permissions: number;
            assignedUsers: number;
        };
    };
}>();

const page = usePage<{ flash?: { success?: string } }>();
const selectedRoleId = ref(props.accessControl.roles[0]?.role_id ?? 0);
const showCreate = ref(false);
const showDelete = ref(false);
const selectedRole = computed(
    () =>
        props.accessControl.roles.find(
            (role) => role.role_id === selectedRoleId.value,
        ) ?? props.accessControl.roles[0],
);
const permissionGroups = computed(() => {
    const groups = new Map<string, Permission[]>();
    props.accessControl.permissions.forEach((permission) => {
        const items = groups.get(permission.module) ?? [];
        items.push(permission);
        groups.set(permission.module, items);
    });
    return [...groups.entries()];
});

const editForm = useForm({
    role_name: '',
    description: '' as string | null,
    permissions: [] as number[],
});
const createForm = useForm({
    role_code: '',
    role_name: '',
    description: '',
    permissions: [] as number[],
});

watch(
    selectedRole,
    (role) => {
        if (!role) return;
        editForm.role_name = role.role_name;
        editForm.description = role.description;
        editForm.permissions = [...role.permissions];
        editForm.clearErrors();
    },
    { immediate: true },
);

const permissionEnabled = (permissionId: number) =>
    editForm.permissions.includes(permissionId);
const togglePermission = (permissionId: number) => {
    if (selectedRole.value?.role_code === 'super_admin') return;
    editForm.permissions = permissionEnabled(permissionId)
        ? editForm.permissions.filter((id) => id !== permissionId)
        : [...editForm.permissions, permissionId];
};
const saveRole = () => {
    if (!selectedRole.value) return;
    editForm.put(update(selectedRole.value.role_id).url, {
        preserveScroll: true,
    });
};
const createRole = () => {
    createForm.post(store().url, {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            showCreate.value = false;
        },
    });
};
const deleteRole = () => {
    if (!selectedRole.value) return;
    router.delete(destroy(selectedRole.value.role_id).url, {
        preserveScroll: true,
        onSuccess: () => {
            selectedRoleId.value = props.accessControl.roles[0]?.role_id ?? 0;
            showDelete.value = false;
        },
    });
};
const asset = (name: string) => `/images/dashboard/${name}`;
</script>

<template>
    <Head title="Roles & Permissions" />
    <div
        class="min-h-screen bg-[#f8f9ff] [font-family:Inter,ui-sans-serif,system-ui,sans-serif] text-[#0b1c30] xl:flex"
    >
        <aside
            class="flex w-full shrink-0 flex-col justify-between bg-[#213145] text-[#d8e3fb] xl:sticky xl:top-0 xl:h-screen xl:w-64"
        >
            <div>
                <div class="flex h-16 items-center gap-3 px-6">
                    <div
                        class="grid size-9 place-items-center rounded-lg bg-[#0f766e]"
                    >
                        <img :src="asset('sidebar-01.svg')" alt="" />
                    </div>
                    <div>
                        <p class="font-semibold text-[#eaf1ff]">Progress LMS</p>
                        <p
                            class="text-[11px] font-medium tracking-[.05em] text-[#bcc7de] uppercase"
                        >
                            University Admin
                        </p>
                    </div>
                </div>
                <nav class="grid gap-1 px-4 py-3 text-[13px] font-medium">
                    <Link
                        :href="dashboard().url"
                        class="flex items-center gap-3 rounded-lg p-2 hover:bg-white/5"
                    >
                        <img
                            :src="asset('sidebar-02.svg')"
                            alt=""
                            class="size-[18px]"
                        />Dashboard
                    </Link>
                    <span class="flex items-center gap-3 rounded-lg p-2">
                        <img
                            :src="asset('sidebar-03.svg')"
                            alt=""
                            class="size-[18px]"
                        />Users
                    </span>
                    <span class="flex items-center gap-3 rounded-lg p-2">
                        <img
                            :src="asset('sidebar-04.svg')"
                            alt=""
                            class="size-[18px]"
                        />Academics
                    </span>
                    <span class="flex items-center gap-3 rounded-lg p-2">
                        <img
                            :src="asset('sidebar-05.svg')"
                            alt=""
                            class="size-[18px]"
                        />Classes
                    </span>
                    <span class="flex items-center gap-3 rounded-lg p-2">
                        <img
                            :src="asset('sidebar-06.svg')"
                            alt=""
                            class="size-[18px]"
                        />Reports
                    </span>
                    <span
                        class="flex items-center gap-3 rounded-lg bg-[#0f766e] p-2 text-[#a3faef]"
                    >
                        <img
                            :src="asset('sidebar-08.svg')"
                            alt=""
                            class="size-[18px]"
                        />Roles &amp; Permissions
                    </span>
                </nav>
            </div>
            <div class="flex items-center gap-3 p-6">
                <div
                    class="grid size-9 place-items-center rounded-full bg-[#0f766e]"
                >
                    <img :src="asset('sidebar-11.svg')" alt="" class="size-3" />
                </div>
                <div class="min-w-0">
                    <p class="truncate text-[13px] font-medium text-white">
                        {{ accessControl.user.name }}
                    </p>
                    <p class="truncate text-[11px] text-[#bcc7de]">
                        {{ accessControl.user.role }}
                    </p>
                </div>
            </div>
        </aside>

        <div class="min-w-0 flex-1">
            <header
                class="flex h-16 items-center justify-between bg-white/80 px-6 shadow-[0_1px_8px_rgba(0,0,0,.04)] backdrop-blur"
            >
                <div>
                    <p class="text-sm font-semibold">Identity &amp; Access</p>
                    <p class="text-xs text-[#596579]">
                        Root-level authorization controls
                    </p>
                </div>
                <span
                    class="rounded-full bg-[#d7f5ec] px-3 py-1.5 text-xs font-semibold text-[#006b56]"
                    >Super Administrator</span
                >
            </header>

            <main class="grid gap-6 p-6">
                <section
                    class="flex flex-col justify-between gap-4 rounded-xl bg-white p-6 shadow-sm lg:flex-row lg:items-center"
                >
                    <div>
                        <p
                            class="mb-2 text-xs font-semibold tracking-[.08em] text-[#0f766e] uppercase"
                        >
                            Access governance
                        </p>
                        <h1 class="text-3xl font-bold tracking-[-.03em]">
                            Roles &amp; Permissions
                        </h1>
                        <p class="mt-2 max-w-2xl text-sm text-[#596579]">
                            Create roles and decide which LMS modules and
                            actions each role can access. System roles remain
                            protected.
                        </p>
                    </div>
                    <button
                        class="rounded-lg bg-[#0f766e] px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-[#0b675f]"
                        @click="showCreate = !showCreate"
                    >
                        + Create Custom Role
                    </button>
                </section>

                <p
                    v-if="page.props.flash?.success"
                    class="rounded-lg border border-[#a6e6d7] bg-[#e4f8f2] px-4 py-3 text-sm font-medium text-[#006b56]"
                >
                    {{ page.props.flash.success }}
                </p>

                <section
                    class="grid grid-cols-2 gap-3 lg:grid-cols-4"
                    aria-label="Access control summary"
                >
                    <article
                        v-for="item in [
                            ['Total Roles', accessControl.summary.roles],
                            ['Custom Roles', accessControl.summary.customRoles],
                            ['Permissions', accessControl.summary.permissions],
                            [
                                'Assigned Users',
                                accessControl.summary.assignedUsers,
                            ],
                        ]"
                        :key="item[0]"
                        class="rounded-xl bg-white p-5 shadow-sm"
                    >
                        <p
                            class="text-xs font-semibold text-[#596579] uppercase"
                        >
                            {{ item[0] }}
                        </p>
                        <strong class="mt-4 block text-3xl">{{
                            item[1]
                        }}</strong>
                    </article>
                </section>

                <form
                    v-if="showCreate"
                    class="grid gap-4 rounded-xl border border-[#b8c5d9] bg-white p-6 shadow-sm"
                    @submit.prevent="createRole"
                >
                    <div>
                        <h2 class="text-lg font-bold">Create a custom role</h2>
                        <p class="text-sm text-[#596579]">
                            The code is permanent and is used by authorization
                            checks.
                        </p>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="grid gap-1.5 text-sm font-medium"
                            >Role name
                            <input
                                v-model="createForm.role_name"
                                required
                                class="h-11 rounded-lg border border-[#c3ccda] px-3 outline-none focus:border-[#0f766e]"
                                placeholder="Content Moderator"
                            />
                            <span class="text-xs text-red-700">{{
                                createForm.errors.role_name
                            }}</span>
                        </label>
                        <label class="grid gap-1.5 text-sm font-medium"
                            >Role code
                            <input
                                v-model="createForm.role_code"
                                required
                                pattern="[a-z][a-z_]{1,29}"
                                class="h-11 rounded-lg border border-[#c3ccda] px-3 font-mono outline-none focus:border-[#0f766e]"
                                placeholder="content_moderator"
                            />
                            <span class="text-xs text-red-700">{{
                                createForm.errors.role_code
                            }}</span>
                        </label>
                    </div>
                    <label class="grid gap-1.5 text-sm font-medium"
                        >Description
                        <textarea
                            v-model="createForm.description"
                            class="min-h-20 rounded-lg border border-[#c3ccda] p-3 outline-none focus:border-[#0f766e]"
                            placeholder="Describe what this role is responsible for"
                        ></textarea>
                    </label>
                    <div class="flex justify-end gap-3">
                        <button
                            type="button"
                            class="rounded-lg border border-[#c3ccda] px-4 py-2 text-sm font-semibold"
                            @click="showCreate = false"
                        >
                            Cancel
                        </button>
                        <button
                            :disabled="createForm.processing"
                            class="rounded-lg bg-[#0f766e] px-5 py-2 text-sm font-semibold text-white disabled:opacity-50"
                        >
                            Create Role
                        </button>
                    </div>
                </form>

                <section class="grid gap-6 xl:grid-cols-[320px_1fr]">
                    <aside class="rounded-xl bg-white p-3 shadow-sm">
                        <div class="px-3 py-3">
                            <h2 class="font-bold">Role directory</h2>
                            <p class="text-xs text-[#596579]">
                                {{ accessControl.roles.length }} active roles
                            </p>
                        </div>
                        <div class="grid gap-1">
                            <button
                                v-for="role in accessControl.roles"
                                :key="role.role_id"
                                class="rounded-lg p-3 text-left transition"
                                :class="
                                    selectedRoleId === role.role_id
                                        ? 'bg-[#e3f4f1] ring-1 ring-[#9ad8cf]'
                                        : 'hover:bg-[#f4f6fb]'
                                "
                                @click="selectedRoleId = role.role_id"
                            >
                                <span
                                    class="flex items-center justify-between gap-2"
                                >
                                    <strong class="text-sm">{{
                                        role.role_name
                                    }}</strong>
                                    <span
                                        v-if="role.is_system"
                                        class="rounded bg-[#edf1f8] px-2 py-0.5 text-[10px] font-semibold text-[#48566a] uppercase"
                                        >System</span
                                    >
                                </span>
                                <span
                                    class="mt-1 block font-mono text-[11px] text-[#66758a]"
                                    >{{ role.role_code }}</span
                                >
                                <span class="mt-2 block text-xs text-[#596579]"
                                    >{{ role.users_count }} users ·
                                    {{ role.permissions.length }}
                                    permissions</span
                                >
                            </button>
                        </div>
                    </aside>

                    <div
                        v-if="selectedRole"
                        class="grid gap-5 rounded-xl bg-white p-6 shadow-sm"
                    >
                        <div
                            class="flex flex-col justify-between gap-3 border-b border-[#e0e5ee] pb-5 sm:flex-row sm:items-start"
                        >
                            <div>
                                <div class="flex items-center gap-2">
                                    <h2 class="text-xl font-bold">
                                        {{ selectedRole.role_name }}
                                    </h2>
                                    <span
                                        v-if="
                                            selectedRole.role_code ===
                                            'super_admin'
                                        "
                                        class="rounded-full bg-[#d7f5ec] px-2.5 py-1 text-[10px] font-bold text-[#006b56] uppercase"
                                        >Full access locked</span
                                    >
                                </div>
                                <p
                                    class="mt-1 font-mono text-xs text-[#66758a]"
                                >
                                    {{ selectedRole.role_code }}
                                </p>
                            </div>
                            <button
                                v-if="!selectedRole.is_system"
                                class="rounded-lg border border-[#e5b8b8] px-3 py-2 text-xs font-semibold text-[#a42020]"
                                @click="showDelete = true"
                            >
                                Delete role
                            </button>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="grid gap-1.5 text-sm font-medium"
                                >Display name
                                <input
                                    v-model="editForm.role_name"
                                    :disabled="selectedRole.is_system"
                                    class="h-11 rounded-lg border border-[#c3ccda] px-3 outline-none disabled:bg-[#f0f3f8]"
                                />
                            </label>
                            <label class="grid gap-1.5 text-sm font-medium"
                                >Description
                                <input
                                    v-model="editForm.description"
                                    :disabled="selectedRole.is_system"
                                    class="h-11 rounded-lg border border-[#c3ccda] px-3 outline-none disabled:bg-[#f0f3f8]"
                                />
                            </label>
                        </div>

                        <div>
                            <div class="flex items-end justify-between gap-3">
                                <div>
                                    <h3 class="font-bold">Permission matrix</h3>
                                    <p class="text-sm text-[#596579]">
                                        Toggle the exact actions granted to this
                                        role.
                                    </p>
                                </div>
                                <span
                                    class="text-xs font-semibold text-[#0f766e]"
                                    >{{ editForm.permissions.length }} of
                                    {{ accessControl.permissions.length }}
                                    enabled</span
                                >
                            </div>
                            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                                <fieldset
                                    v-for="[
                                        module,
                                        permissions,
                                    ] in permissionGroups"
                                    :key="module"
                                    class="rounded-xl border border-[#dce2ec] p-4"
                                >
                                    <legend
                                        class="px-2 text-xs font-bold tracking-[.06em] text-[#415169] uppercase"
                                    >
                                        {{ module }}
                                    </legend>
                                    <label
                                        v-for="permission in permissions"
                                        :key="permission.permission_id"
                                        class="flex cursor-pointer items-start gap-3 rounded-lg p-2 hover:bg-[#f6f8fc]"
                                    >
                                        <input
                                            type="checkbox"
                                            class="mt-1 size-4 accent-[#0f766e]"
                                            :checked="
                                                permissionEnabled(
                                                    permission.permission_id,
                                                )
                                            "
                                            :disabled="
                                                selectedRole.role_code ===
                                                'super_admin'
                                            "
                                            @change="
                                                togglePermission(
                                                    permission.permission_id,
                                                )
                                            "
                                        />
                                        <span>
                                            <strong
                                                class="block font-mono text-xs"
                                                >{{
                                                    permission.permission_code
                                                }}</strong
                                            >
                                            <span
                                                class="mt-0.5 block text-xs leading-5 text-[#66758a]"
                                                >{{
                                                    permission.description
                                                }}</span
                                            >
                                        </span>
                                    </label>
                                </fieldset>
                            </div>
                        </div>

                        <div
                            class="flex items-center justify-between border-t border-[#e0e5ee] pt-5"
                        >
                            <p class="text-xs text-[#66758a]">
                                Changes apply to all users assigned to this
                                role.
                            </p>
                            <button
                                :disabled="
                                    editForm.processing ||
                                    selectedRole.role_code === 'super_admin'
                                "
                                class="rounded-lg bg-[#0f766e] px-5 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:bg-[#9aa7b8]"
                                @click="saveRole"
                            >
                                Save Permissions
                            </button>
                        </div>
                    </div>
                </section>
            </main>
        </div>

        <div
            v-if="showDelete && selectedRole"
            class="fixed inset-0 z-50 grid place-items-center bg-[#07121f]/55 p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="delete-role-title"
        >
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-2xl">
                <h2 id="delete-role-title" class="text-xl font-bold">
                    Delete {{ selectedRole.role_name }}?
                </h2>
                <p class="mt-2 text-sm leading-6 text-[#596579]">
                    The role and all of its permission grants will be removed.
                    Roles with assigned users cannot be deleted.
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <button
                        class="rounded-lg border border-[#c3ccda] px-4 py-2 text-sm font-semibold"
                        @click="showDelete = false"
                    >
                        Cancel
                    </button>
                    <button
                        class="rounded-lg bg-[#b3261e] px-4 py-2 text-sm font-semibold text-white"
                        @click="deleteRole"
                    >
                        Delete Role
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
