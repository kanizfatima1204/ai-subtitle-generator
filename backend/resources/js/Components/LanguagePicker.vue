<script setup>
defineProps({
    file: { type: Object, required: true },
    previewUrl: { type: String, required: true },
    languages: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
});
const language = defineModel({ type: String, default: 'en' });
const emit = defineEmits(['back', 'start']);
</script>

<template>
    <main class="min-h-screen bg-[#101112] px-4 py-6 text-white sm:px-8 lg:px-12">
        <header class="mx-auto mb-8 flex max-w-[1500px] items-center justify-between">
            <button class="text-sm text-white/60 hover:text-white" @click="emit('back')">← Back</button>
            <span class="text-sm font-semibold tracking-wide">Subtitle Lab</span>
            <span class="w-12"></span>
        </header>
        <div class="mx-auto grid max-w-[1500px] gap-3 lg:min-h-[min(78vh,820px)] lg:grid-cols-[1.15fr_1fr]">
            <section class="rounded-xl bg-[#171819] p-6 sm:p-10 lg:p-12">
                <h1 class="mb-10 text-3xl font-bold sm:text-4xl">Select your language</h1>
                <div class="flex flex-wrap gap-3 sm:gap-4">
                    <button
                        v-for="item in languages" :key="item.code"
                        class="rounded-full border px-5 py-3 text-sm transition sm:px-7 sm:text-base"
                        :class="language === item.code ? 'border-emerald-400/60 bg-emerald-400/15 text-emerald-300' : 'border-white/15 bg-white/[0.035] hover:border-white/35'"
                        @click="language = item.code"
                    >
                        <span v-if="language === item.code" class="mr-2">✓</span>{{ item.name }}
                    </button>
                </div>
                <div class="mt-10 flex flex-wrap items-center justify-between gap-4 border-t border-white/10 pt-6">
                    <p class="max-w-[60%] truncate text-xs text-white/40" :title="file.name">{{ file.name }}</p>
                    <button class="rounded-xl bg-emerald-500 px-6 py-3 font-semibold text-[#07130e] transition hover:bg-emerald-400 disabled:opacity-50" :disabled="loading" @click="emit('start')">
                        {{ loading ? 'Starting…' : 'Generate captions' }}
                    </button>
                </div>
            </section>
            <section class="relative flex min-h-[360px] items-center justify-center overflow-hidden rounded-xl bg-[#171819] p-3">
                <video v-if="file.type.startsWith('video/')" :src="previewUrl" class="h-full max-h-[78vh] w-full rounded-lg object-contain" controls playsinline />
                <div v-else class="w-full max-w-xl rounded-2xl border border-white/10 bg-black/25 p-8 text-center">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-400/10 text-emerald-300">♫</div>
                    <p class="font-medium">Audio preview</p>
                    <audio :src="previewUrl" class="mt-6 w-full" controls />
                </div>
                <p class="pointer-events-none absolute bottom-8 left-1/2 max-w-[85%] -translate-x-1/2 rounded-md bg-white px-3 py-1.5 text-center text-xl font-bold text-black shadow-lg">We have captions on screen</p>
            </section>
        </div>
    </main>
</template>
