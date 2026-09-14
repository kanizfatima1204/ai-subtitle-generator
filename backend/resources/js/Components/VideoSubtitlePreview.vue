<script setup>
import { computed, nextTick, ref, watch } from 'vue';

const props = defineProps({
    videoUrl: {
        type: String,
        default: '',
    },

    subtitles: {
        type: Array,
        default: () => [],
    },
});

const video = ref(null);
const currentTime = ref(0);
const duration = ref(0);

const activeSubtitle = computed(() => {
    return props.subtitles.find(
        (subtitle) =>
            currentTime.value >= Number(subtitle.start) &&
            currentTime.value <= Number(subtitle.end)
    );
});

function formatTime(seconds) {
    seconds = Math.max(0, Number(seconds) || 0);

    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = Math.floor(seconds % 60);

    return `${String(minutes).padStart(2, '0')}:${String(
        remainingSeconds
    ).padStart(2, '0')}`;
}

function updateTime() {
    if (!video.value) return;

    currentTime.value = video.value.currentTime;
}

function loadMetadata() {
    if (!video.value) return;

    duration.value = video.value.duration || 0;
}

function seekTo(time) {
    if (!video.value) return;

    video.value.currentTime = Number(time);
    currentTime.value = Number(time);
}

function togglePlay() {
    if (!video.value) return;

    if (video.value.paused) {
        video.value.play();
    } else {
        video.value.pause();
    }
}

function handleTimelineClick(event) {
    if (!video.value || !duration.value) return;

    const rect =
        event.currentTarget.getBoundingClientRect();

    const position =
        (event.clientX - rect.left) / rect.width;

    seekTo(position * duration.value);
}

watch(
    () => props.videoUrl,
    async () => {
        await nextTick();

        if (video.value) {
            video.value.load();
        }
    }
);
</script>

<template>
    <div
        class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
    >
        <!-- Video -->
        <div class="relative aspect-video bg-black">
            <video
                ref="video"
                class="h-full w-full object-contain"
                :src="videoUrl"
                controls
                preload="metadata"
                @timeupdate="updateTime"
                @loadedmetadata="loadMetadata"
            />

            <!-- Subtitle Overlay -->
            <div
                v-if="activeSubtitle"
                class="pointer-events-none absolute bottom-14 left-0 right-0 flex justify-center px-6"
            >
                <div
                    class="max-w-3xl rounded-lg bg-black/80 px-5 py-3 text-center text-lg font-semibold leading-relaxed text-white shadow-lg"
                >
                    {{ activeSubtitle.text }}
                </div>
            </div>
        </div>

        <!-- Player Controls -->
        <div
            class="border-t border-gray-200 p-4 dark:border-gray-800"
        >
            <div class="flex items-center gap-4">
                <button
                    type="button"
                    @click="togglePlay"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700"
                >
                    Play / Pause
                </button>

                <div class="text-sm text-gray-600 dark:text-gray-400">
                    {{ formatTime(currentTime) }}
                    /
                    {{ formatTime(duration) }}
                </div>
            </div>
        </div>

        <!-- Timeline -->
        <div
            class="border-t border-gray-200 p-4 dark:border-gray-800"
        >
            <div
                class="mb-3 flex items-center justify-between"
            >
                <h3
                    class="text-sm font-semibold text-gray-900 dark:text-white"
                >
                    Subtitle Timeline
                </h3>

                <span
                    class="text-xs text-gray-500 dark:text-gray-400"
                >
                    {{ subtitles.length }} subtitles
                </span>
            </div>

            <!-- Timeline Track -->
            <div
                class="relative h-16 cursor-pointer rounded-lg bg-gray-100 dark:bg-gray-800"
                @click="handleTimelineClick"
            >
                <template
                    v-for="subtitle in subtitles"
                    :key="subtitle.id ?? subtitle.sequence"
                >
                    <button
                        type="button"
                        class="absolute top-2 h-12 overflow-hidden rounded-md border border-indigo-400 bg-indigo-500/70 px-2 text-left text-xs text-white transition hover:bg-indigo-600"
                        :class="{
                            'ring-2 ring-indigo-300':
                                activeSubtitle?.id === subtitle.id,
                        }"
                        :style="{
                            left:
                                duration > 0
                                    ? `${(Number(subtitle.start) / duration) * 100}%`
                                    : '0%',
                            width:
                                duration > 0
                                    ? `${Math.max(
                                          ((Number(subtitle.end) -
                                              Number(subtitle.start)) /
                                              duration) *
                                              100,
                                          1
                                      )}%`
                                    : '1%',
                        }"
                        @click.stop="
                            seekTo(Number(subtitle.start))
                        "
                    >
                        <span class="block truncate">
                            {{ subtitle.text }}
                        </span>
                    </button>
                </template>

                <!-- Current Position -->
                <div
                    v-if="duration > 0"
                    class="pointer-events-none absolute top-0 h-full w-0.5 bg-red-500"
                    :style="{
                        left: `${(currentTime / duration) * 100}%`,
                    }"
                />
            </div>
        </div>

        <!-- Subtitle List -->
        <div
            class="max-h-72 overflow-y-auto border-t border-gray-200 dark:border-gray-800"
        >
            <button
                v-for="subtitle in subtitles"
                :key="subtitle.id ?? subtitle.sequence"
                type="button"
                @click="seekTo(Number(subtitle.start))"
                class="flex w-full items-start gap-4 border-b border-gray-100 p-4 text-left transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800"
                :class="{
                    'bg-indigo-50 dark:bg-indigo-950/30':
                        activeSubtitle?.id === subtitle.id,
                }"
            >
                <div
                    class="w-16 shrink-0 text-xs font-medium text-indigo-600 dark:text-indigo-400"
                >
                    {{ formatTime(subtitle.start) }}
                </div>

                <div
                    class="flex-1 text-sm text-gray-800 dark:text-gray-200"
                >
                    {{ subtitle.text }}
                </div>
            </button>

            <div
                v-if="subtitles.length === 0"
                class="p-8 text-center text-sm text-gray-500"
            >
                No subtitles available.
            </div>
        </div>
    </div>
</template>
