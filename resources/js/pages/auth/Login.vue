<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Welcome back',
        description: 'Sign in to your Progress LMS account.',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const showPassword = ref(false);
</script>

<template>
    <Head title="Sign in" />

    <div
        v-if="status"
        class="rounded-lg bg-[#eff4ff] px-4 py-3 text-center text-sm font-medium text-[#005c55]"
        role="status"
    >
        {{ status }}
    </div>

    <button
        type="button"
        class="flex h-12 w-full items-center justify-center gap-2.5 rounded-lg bg-[#eff4ff] px-4 text-sm font-semibold text-[#0b1c30]"
        disabled
        title="University single sign-on is not configured"
    >
        <span
            class="flex size-[18px] items-center justify-start"
            aria-hidden="true"
        >
            <img
                src="/images/auth/university.svg"
                alt=""
                width="15"
                height="14"
            />
        </span>
        Continue with University SSO
    </button>

    <div class="flex items-center gap-3 text-xs leading-[15px] text-[#545f73]">
        <span class="h-px flex-1 bg-[#dce2ec]"></span>
        <span>or sign in with email</span>
        <span class="h-px flex-1 bg-[#dce2ec]"></span>
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-5"
    >
        <div class="flex flex-col gap-2">
            <label for="email" class="text-sm leading-[17px] font-medium"
                >University email</label
            >
            <div
                class="flex h-12 items-center gap-3 rounded-lg border border-[#dce2ec] bg-white px-3.5 transition focus-within:border-[#005c55] focus-within:ring-2 focus-within:ring-[#005c55]/15"
                :class="{ 'border-red-500': errors.email }"
            >
                <img
                    src="/images/auth/mail.svg"
                    alt=""
                    width="18"
                    height="18"
                />
                <input
                    id="email"
                    type="email"
                    name="email"
                    required
                    v-focus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="name@university.edu"
                    class="min-w-0 flex-1 bg-transparent text-sm text-[#0b1c30] outline-none placeholder:text-[#545f73]"
                    :aria-invalid="Boolean(errors.email)"
                />
            </div>
            <InputError :message="errors.email" />
        </div>

        <div class="flex flex-col gap-2">
            <label for="password" class="text-sm leading-[17px] font-medium"
                >Password</label
            >
            <div
                class="flex h-12 items-center gap-3 rounded-lg border border-[#dce2ec] bg-white px-3.5 transition focus-within:border-[#005c55] focus-within:ring-2 focus-within:ring-[#005c55]/15"
                :class="{ 'border-red-500': errors.password }"
            >
                <img
                    src="/images/auth/lock-keyhole.svg"
                    alt=""
                    width="18"
                    height="18"
                />
                <input
                    id="password"
                    :type="showPassword ? 'text' : 'password'"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Enter your password"
                    class="min-w-0 flex-1 bg-transparent text-sm text-[#0b1c30] outline-none placeholder:text-[#545f73]"
                    :aria-invalid="Boolean(errors.password)"
                />
                <button
                    type="button"
                    class="rounded-sm focus-visible:ring-2 focus-visible:ring-[#005c55] focus-visible:outline-none"
                    :aria-label="
                        showPassword ? 'Hide password' : 'Show password'
                    "
                    @click="showPassword = !showPassword"
                >
                    <img
                        src="/images/auth/eye.svg"
                        alt=""
                        width="18"
                        height="18"
                    />
                </button>
            </div>
            <InputError :message="errors.password" />
        </div>

        <div
            class="flex items-center justify-between gap-4 text-[13px] leading-4"
        >
            <label
                for="remember"
                class="flex items-center gap-2 text-[#545f73]"
            >
                <Checkbox
                    id="remember"
                    name="remember"
                    :tabindex="3"
                    class="border-[#a8b3c3]"
                />
                <span>Remember me</span>
            </label>
            <Link
                v-if="canResetPassword"
                :href="request()"
                class="font-medium text-[#005c55] hover:underline"
                :tabindex="5"
            >
                Forgot password?
            </Link>
        </div>

        <button
            type="submit"
            class="flex h-12 w-full items-center justify-center gap-2.5 rounded-lg bg-[#005c55] px-4 text-sm font-semibold text-white transition hover:bg-[#004b46] focus-visible:ring-2 focus-visible:ring-[#005c55] focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-70"
            :tabindex="4"
            :disabled="processing"
            data-test="login-button"
        >
            <Spinner v-if="processing" />
            <span>{{ processing ? 'Signing in...' : 'Sign in' }}</span>
            <img
                v-if="!processing"
                src="/images/auth/arrow-right.svg"
                alt=""
                width="18"
                height="18"
            />
        </button>
    </Form>

    <div class="flex flex-col items-center gap-1 text-[13px] leading-[15px]">
        <p class="text-[#545f73]">New here or having trouble signing in?</p>
        <p class="font-medium text-[#005c55]">Contact your campus IT team</p>
    </div>
</template>
