<script setup lang="ts">
defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <main
        class="flex min-h-svh flex-col items-center justify-between gap-6 bg-[#f8f9ff] px-5 pt-5 pb-6 [line-height:normal] text-[#0b1c30] sm:px-10 sm:pt-8 sm:pb-7"
        style="font-family: Inter, ui-sans-serif, system-ui, sans-serif"
    >
        <div class="flex w-full items-start justify-end">
            <div
                class="flex items-center gap-2 rounded-full bg-[#eff4ff] px-3 py-1.5"
                aria-label="Service status: all systems operational"
            >
                <span class="size-[7px] rounded-full bg-[#007952]"></span>
                <span class="text-xs font-medium text-[#005c55]">
                    All Systems Operational
                </span>
            </div>
        </div>

        <div class="flex w-full max-w-[468px] flex-col items-center gap-6">
            <section
                class="flex w-full flex-col gap-6 rounded-lg border border-[#e9edf4] bg-white p-6 shadow-[0_4px_20px_rgba(33,49,69,0.04)] sm:p-8"
            >
                <header class="flex flex-col gap-2">
                    <h1
                        class="text-[28px] leading-9 font-semibold tracking-[-0.02em]"
                    >
                        {{ title }}
                    </h1>
                    <p class="text-sm leading-[22px] text-[#545f73]">
                        {{ description }}
                    </p>
                </header>

                <slot />
            </section>

            <div class="flex items-center gap-2 text-xs text-[#545f73]">
                <img
                    src="/images/auth/shield-check.svg"
                    alt=""
                    width="16"
                    height="16"
                />
                <span
                    >Secure access for students, faculty, and
                    administrators</span
                >
            </div>
        </div>

        <footer
            class="flex w-full flex-col items-center justify-between gap-3 text-xs text-[#545f73] sm:flex-row"
        >
            <div class="flex items-center gap-1.5">
                <img
                    src="/images/auth/circle-help.svg"
                    alt=""
                    width="16"
                    height="16"
                />
                <span>Support &amp; Docs</span>
            </div>
            <div class="flex items-center gap-6">
                <span>Privacy policy</span>
                <span>Terms of use</span>
            </div>
        </footer>
    </main>
</template>
