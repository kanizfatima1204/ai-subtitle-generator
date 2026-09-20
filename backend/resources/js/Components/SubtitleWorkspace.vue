<script setup>
import {
    computed,
    ref,
    watch,
} from 'vue';

import api from '@/lib/api';

const props = defineProps({
    job: {
        type: Object,
        required: true,
    },

    subtitles: {
        type: Array,
        default: () => [],
    },

    videoUrl: {
        type: String,
        default: null,
    },

    saving: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits([
    'save',
    'download',
    'add-subtitle',
    'delete-subtitle',
    'duplicate-subtitle',
    'new-project',
    'logout',
]);

/*
|--------------------------------------------------------------------------
| General Workspace State
|--------------------------------------------------------------------------
*/

const search = ref('');
const selectedId = ref(null);

const localSubtitles = ref([]);

const video = ref(null);

const currentTime = ref(0);
const duration = ref(0);
const isPlaying = ref(false);

/*
|--------------------------------------------------------------------------
| AI Assistant State
|--------------------------------------------------------------------------
*/

const aiOpen = ref(false);
const aiLoading = ref(false);
const aiResult = ref('');
const aiAction = ref('');
const aiError = ref('');
const exportLoading = ref('');
const exportError = ref('');

/*
|--------------------------------------------------------------------------
| Sync Props -> Local Editor
|--------------------------------------------------------------------------
*/

watch(
    () => props.subtitles,
    (value) => {
        localSubtitles.value =
            JSON.parse(
                JSON.stringify(value || [])
            );

        if (
            selectedId.value === null &&
            localSubtitles.value.length
        ) {
            selectedId.value =
                localSubtitles.value[0].id ??
                localSubtitles.value[0].sequence;
        }

        /*
         * If currently selected subtitle no longer exists,
         * select the first available subtitle.
         */
        const selectedExists =
            localSubtitles.value.some(
                (subtitle) =>
                    (
                        subtitle.id ??
                        subtitle.sequence
                    ) === selectedId.value
            );

        if (
            !selectedExists &&
            localSubtitles.value.length
        ) {
            selectedId.value =
                localSubtitles.value[0].id ??
                localSubtitles.value[0].sequence;
        }

        if (!localSubtitles.value.length) {
            selectedId.value = null;
        }
    },
    {
        immediate: true,
        deep: true,
    }
);

/*
|--------------------------------------------------------------------------
| Computed
|--------------------------------------------------------------------------
*/

const filteredSubtitles = computed(() => {
    const keyword =
        search.value
            .trim()
            .toLowerCase();

    if (!keyword) {
        return localSubtitles.value;
    }

    return localSubtitles.value.filter(
        (subtitle) =>
            subtitle.text
                ?.toLowerCase()
                .includes(keyword)
    );
});

const selectedSubtitle = computed(() => {
    return (
        localSubtitles.value.find(
            (subtitle) =>
                (
                    subtitle.id ??
                    subtitle.sequence
                ) === selectedId.value
        ) || null
    );
});

const activeSubtitle = computed(() => {
    return (
        localSubtitles.value.find(
            (subtitle) =>
                currentTime.value >=
                    Number(
                        subtitle.start_time
                    ) &&
                currentTime.value <=
                    Number(
                        subtitle.end_time
                    )
        ) || null
    );
});

const timelinePosition = computed(() => {
    if (!duration.value) {
        return 0;
    }

    return Math.min(
        100,
        Math.max(
            0,
            (
                currentTime.value /
                duration.value
            ) * 100
        )
    );
});

/*
|--------------------------------------------------------------------------
| Subtitle Selection
|--------------------------------------------------------------------------
*/

function selectSubtitle(subtitle) {
    selectedId.value =
        subtitle.id ??
        subtitle.sequence;

    if (video.value) {
        video.value.currentTime =
            Number(
                subtitle.start_time
            );
    }

    currentTime.value =
        Number(
            subtitle.start_time
        );
}

/*
|--------------------------------------------------------------------------
| Editor Updates
|--------------------------------------------------------------------------
*/

function updateSelected(
    field,
    value
) {
    if (!selectedSubtitle.value) {
        return;
    }

    selectedSubtitle.value[field] =
        value;
}

/*
|--------------------------------------------------------------------------
| Time Formatting
|--------------------------------------------------------------------------
*/

function formatTime(seconds) {
    const value =
        Math.max(
            0,
            Number(seconds) || 0
        );

    const minutes =
        Math.floor(
            value / 60
        );

    const secs =
        Math.floor(
            value % 60
        );

    const ms =
        Math.floor(
            (value % 1) * 1000
        );

    return `${String(minutes).padStart(
        2,
        '0'
    )}:${String(secs).padStart(
        2,
        '0'
    )}.${String(ms).padStart(
        3,
        '0'
    )}`;
}

/*
|--------------------------------------------------------------------------
| Video Controls
|--------------------------------------------------------------------------
*/

function seek(time) {
    if (!video.value) {
        return;
    }

    const target =
        Math.max(
            0,
            Math.min(
                Number(time),
                duration.value ||
                    Number(time)
            )
        );

    video.value.currentTime =
        target;

    currentTime.value =
        target;
}

function togglePlay() {
    if (!video.value) {
        return;
    }

    if (video.value.paused) {
        video.value.play();
    } else {
        video.value.pause();
    }
}

function handleTimeUpdate() {
    if (!video.value) {
        return;
    }

    currentTime.value =
        video.value.currentTime;
}

function handleLoadedMetadata() {
    if (!video.value) {
        return;
    }

    duration.value =
        video.value.duration || 0;
}

function handlePlay() {
    isPlaying.value = true;
}

function handlePause() {
    isPlaying.value = false;
}

/*
|--------------------------------------------------------------------------
| Timeline Click
|--------------------------------------------------------------------------
*/

function handleTimelineClick(event) {
    if (!duration.value) {
        return;
    }

    const element =
        event.currentTarget;

    const rect =
        element.getBoundingClientRect();

    const position =
        (
            event.clientX -
            rect.left
        ) / rect.width;

    seek(
        position *
            duration.value
    );
}

/*
|--------------------------------------------------------------------------
| Save
|--------------------------------------------------------------------------
*/

function save() {
    emit(
        'save',
        JSON.parse(
            JSON.stringify(
                localSubtitles.value
            )
        )
    );
}

/*
|--------------------------------------------------------------------------
| Add Subtitle
|--------------------------------------------------------------------------
*/

function addSubtitle() {
    emit('add-subtitle');
}

/*
|--------------------------------------------------------------------------
| Duplicate Subtitle
|--------------------------------------------------------------------------
*/

function duplicateSubtitle() {
    if (!selectedSubtitle.value) {
        return;
    }

    emit(
        'duplicate-subtitle',
        JSON.parse(
            JSON.stringify(
                selectedSubtitle.value
            )
        )
    );
}

/*
|--------------------------------------------------------------------------
| Delete Subtitle
|--------------------------------------------------------------------------
*/

function deleteSubtitle() {
    if (!selectedSubtitle.value) {
        return;
    }

    const currentIndex =
        localSubtitles.value.findIndex(
            (subtitle) =>
                (
                    subtitle.id ??
                    subtitle.sequence
                ) === selectedId.value
        );

    emit(
        'delete-subtitle',
        selectedSubtitle.value.id
    );

    const nextSubtitle =
        localSubtitles.value[
            currentIndex + 1
        ] ||
        localSubtitles.value[
            currentIndex - 1
        ];

    if (nextSubtitle) {
        selectedId.value =
            nextSubtitle.id ??
            nextSubtitle.sequence;
    } else {
        selectedId.value = null;
    }
}

/*
|--------------------------------------------------------------------------
| Download
|--------------------------------------------------------------------------
*/

async function download() {
    await exportFile('srt');
}

async function exportFile(format) {
    exportLoading.value = format;
    exportError.value = '';

    try {
        const response =
            await api.get(
                `/api/subtitle-jobs/${props.job.id}/export/${format}`,
                {
                    responseType: 'blob',
                }
            );

        const blob =
            new Blob(
                [response.data],
                {
                    type: format === 'vtt'
                        ? 'text/vtt'
                        : format === 'txt'
                            ? 'text/plain'
                            : format === 'mp4'
                                ? 'video/mp4'
                                : 'application/x-subrip',
                }
            );

        const url =
            URL.createObjectURL(blob);

        const link =
            document.createElement(
                'a'
            );

        link.href = url;

        const filename =
            (
                props.job
                    .original_filename ||
                'subtitles'
            )
                .replace(
                    /\.[^/.]+$/,
                    ''
                )
                .replace(
                    /[^a-zA-Z0-9-_]+/g,
                    '-'
                );

        link.download =
            `${filename || 'subtitles'}${
                format === 'mp4'
                    ? '-captioned.mp4'
                    : `.${format}`
            }`;

        document.body.appendChild(
            link
        );

        link.click();

        link.remove();

        URL.revokeObjectURL(url);
    } catch (error) {
        console.error(
            'Export failed:',
            error
        );

        let message =
            error.response?.data?.message;

        if (
            !message &&
            error.response?.data instanceof Blob
        ) {
            try {
                const body =
                    await error.response.data.text();

                message =
                    JSON.parse(body)?.message;
            } catch {
                message = '';
            }
        }

        exportError.value =
            message ||
            `Unable to export ${format.toUpperCase()}.`;
    } finally {
        exportLoading.value = '';
    }
}

/*
|--------------------------------------------------------------------------
| AI Assistant
|--------------------------------------------------------------------------
*/

async function runAIAssist(
    action,
    targetLanguage = null
) {
    if (
        !selectedSubtitle.value ||
        !selectedSubtitle.value.text?.trim()
    ) {
        aiError.value =
            'Please select a subtitle with text first.';

        return;
    }

    aiLoading.value = true;
    aiAction.value = action;
    aiError.value = '';
    aiResult.value = '';

    try {
        const response =
            await api.post(
                `/api/subtitle-jobs/${props.job.id}/ai-assist`,
                {
                    text:
                        selectedSubtitle
                            .value
                            .text,

                    action,

                    target_language:
                        targetLanguage,
                }
            );

        aiResult.value =
            response.data
                ?.result
                ?.text || '';

        if (!aiResult.value) {
            aiError.value =
                'AI did not return a result.';
        }
    } catch (error) {
        console.error(
            'AI Assist error:',
            error
        );

        aiError.value =
            error.response
                ?.data
                ?.message ||
            'AI assistant failed. Please try again.';
    } finally {
        aiLoading.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Apply AI Result
|--------------------------------------------------------------------------
*/

function applyAIResult() {
    if (
        !selectedSubtitle.value ||
        !aiResult.value
    ) {
        return;
    }

    selectedSubtitle.value.text =
        aiResult.value;

    aiResult.value = '';
    aiError.value = '';

    aiOpen.value = false;
}

/*
|--------------------------------------------------------------------------
| Reset AI Panel
|--------------------------------------------------------------------------
*/

function closeAIAssistant() {
    aiOpen.value = false;
    aiResult.value = '';
    aiError.value = '';
    aiAction.value = '';
}

/*
|--------------------------------------------------------------------------
| Job Status
|--------------------------------------------------------------------------
*/

function statusLabel(status) {
    const labels = {
        pending: 'Pending',
        processing: 'Processing',
        completed: 'Completed',
        failed: 'Failed',
    };

    return (
        labels[status] ||
        status ||
        'Unknown'
    );
}
</script>

<template>
    <div
        class="min-h-screen overflow-x-hidden bg-[#070812] text-white"
    >
        <!-- ========================================================= -->
        <!-- Background Effects -->
        <!-- ========================================================= -->

        <div
            class="pointer-events-none fixed inset-0 overflow-hidden"
        >
            <div
                class="absolute -left-40 -top-40 h-[500px] w-[500px] rounded-full bg-cyan-500/[0.06] blur-[140px]"
            ></div>

            <div
                class="absolute -bottom-40 -right-40 h-[500px] w-[500px] rounded-full bg-violet-500/[0.06] blur-[140px]"
            ></div>
        </div>

        <!-- ========================================================= -->
        <!-- Header -->
        <!-- ========================================================= -->

        <header
            class="sticky top-0 z-40 border-b border-white/10 bg-[#070812]/75 backdrop-blur-2xl"
        >
            <div
                class="flex h-16 items-center justify-between px-4 md:px-6"
            >
                <!-- Logo -->
                <button
                    type="button"
                    @click="emit('new-project')"
                    class="group flex cursor-pointer items-center gap-3 transition hover:opacity-85"
                    title="Return to Dashboard / New Project"
                >
                    <div
                        class="flex h-9 w-9 items-center justify-center rounded-xl border border-cyan-300/20 bg-cyan-400/10"
                    >
                        <span
                            class="text-xs font-bold text-cyan-300"
                        >
                            AI
                        </span>
                    </div>

                    <div
                        class="hidden text-left sm:block"
                    >
                        <p
                            class="text-sm font-semibold"
                        >
                            Subtitle Studio
                        </p>

                        <p
                            class="text-[10px] text-white/35"
                        >
                            AI Video Workspace
                        </p>
                    </div>
                </button>

                <!-- Job name -->
                <div
                    class="hidden max-w-[300px] truncate px-4 text-xs text-white/40 lg:block"
                >
                    {{ job.original_filename }}
                </div>

                <!-- Actions -->
                <div
                    class="flex items-center gap-2"
                >
                    <div
                        class="hidden rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-[10px] text-white/45 md:block"
                    >
                        {{ statusLabel(job.status) }}
                    </div>

                    <button
                        type="button"
                        @click="save"
                        :disabled="saving"
                        class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs font-medium transition hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-50 sm:px-4"
                    >
                        <span
                            v-if="saving"
                        >
                            Saving...
                        </span>

                        <span v-else>
                            Save
                        </span>
                    </button>

                    <button
                        type="button"
                        @click="download"
                        :disabled="!!exportLoading"
                        class="rounded-xl bg-[#5fe7bd] px-3 py-2 text-xs font-semibold text-black shadow-[0_0_18px_rgba(95,231,189,0.3)] transition hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-50 sm:px-4"
                    >
                        {{ exportLoading === 'srt' ? 'Preparing...' : 'Export' }}
                    </button>

                    <div class="group relative">
                        <button
                            type="button"
                            :disabled="!!exportLoading"
                            class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs font-medium text-white/75 transition hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            Formats
                            <span class="ml-1 text-white/40">⌄</span>
                        </button>

                        <div class="invisible absolute right-0 top-full z-50 mt-2 w-44 rounded-2xl border border-white/10 bg-[#111820] p-1 opacity-0 shadow-2xl transition group-hover:visible group-hover:opacity-100">
                            <button
                                v-for="format in ['srt', 'vtt', 'txt', 'mp4']"
                                :key="format"
                                type="button"
                                :disabled="!!exportLoading"
                                @click="exportFile(format)"
                                class="flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-left text-xs text-white/70 transition hover:bg-[#5fe7bd]/10 hover:text-[#7df9d7] disabled:opacity-40"
                            >
                                <span>{{ format === 'mp4' ? 'Captioned MP4' : format.toUpperCase() }}</span>
                                <span v-if="exportLoading === format" class="text-[#7df9d7]">...</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div
            v-if="exportError"
            class="fixed right-5 top-20 z-50 max-w-sm rounded-2xl border border-red-300/20 bg-red-500/10 px-4 py-3 text-xs text-red-200 shadow-2xl backdrop-blur-xl"
        >
            <div class="flex items-start gap-3">
                <span class="text-red-300">!</span>
                <span class="flex-1">{{ exportError }}</span>
                <button type="button" @click="exportError = ''" class="text-white/40 hover:text-white">×</button>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- Main -->
        <!-- ========================================================= -->

        <main
            class="relative mx-auto grid max-w-[1900px] grid-cols-1 gap-4 p-4 md:p-6 xl:grid-cols-[250px_minmax(0,1fr)_360px]"
        >
            <!-- ===================================================== -->
            <!-- LEFT SIDEBAR -->
            <!-- ===================================================== -->

            <aside
                class="hidden min-h-[calc(100vh-120px)] flex-col rounded-3xl border border-white/10 bg-white/[0.035] p-4 backdrop-blur-xl xl:flex"
            >
                <!-- Workspace -->
                <div>
                    <p
                        class="px-3 text-[10px] font-semibold uppercase tracking-[0.2em] text-white/30"
                    >
                        Workspace
                    </p>

                    <button
                        type="button"
                        @click="emit('new-project')"
                        class="mt-3 flex w-full cursor-pointer items-center gap-3 rounded-2xl border border-white/5 bg-white/5 px-3 py-3 text-left text-sm transition hover:border-cyan-400/30 hover:bg-white/10"
                        title="Create a new subtitle project"
                    >
                        <span
                            class="flex h-8 w-8 items-center justify-center rounded-xl bg-cyan-400/10 text-lg text-cyan-300 transition group-hover:scale-105"
                        >
                            +
                        </span>

                        <span>
                            New Project
                        </span>
                    </button>
                </div>

                <!-- Project -->
                <div class="mt-8">
                    <p
                        class="px-3 text-[10px] font-semibold uppercase tracking-[0.2em] text-white/30"
                    >
                        Current Project
                    </p>

                    <div
                        class="mt-3 rounded-2xl border border-white/10 bg-white/5 p-3"
                    >
                        <div
                            class="mb-3 flex h-10 w-10 items-center justify-center rounded-xl bg-violet-400/10 text-violet-300"
                        >
                            🎬
                        </div>

                        <p
                            class="truncate text-sm font-medium"
                            :title="
                                job.original_filename
                            "
                        >
                            {{
                                job.original_filename
                            }}
                        </p>

                        <p
                            class="mt-1 text-xs text-white/35"
                        >
                            {{
                                statusLabel(
                                    job.status
                                )
                            }}
                        </p>
                    </div>
                </div>

                <!-- Stats -->
                <div class="mt-6 grid grid-cols-2 gap-2">
                    <div
                        class="rounded-2xl border border-white/5 bg-white/[0.025] p-3"
                    >
                        <p
                            class="text-lg font-semibold"
                        >
                            {{
                                localSubtitles.length
                            }}
                        </p>

                        <p
                            class="mt-1 text-[10px] text-white/30"
                        >
                            Segments
                        </p>
                    </div>

                    <div
                        class="rounded-2xl border border-white/5 bg-white/[0.025] p-3"
                    >
                        <p
                            class="text-lg font-semibold"
                        >
                            {{
                                job.language ||
                                'auto'
                            }}
                        </p>

                        <p
                            class="mt-1 text-[10px] text-white/30"
                        >
                            Language
                        </p>
                    </div>
                </div>

                <!-- Bottom -->
                <div class="mt-auto pt-8">
                    <button
                        type="button"
                        @click="
                            emit('logout')
                        "
                        class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-sm text-white/40 transition hover:bg-red-500/10 hover:text-red-300"
                    >
                        <span>
                            ↪
                        </span>

                        Logout
                    </button>
                </div>
            </aside>

            <!-- ===================================================== -->
            <!-- CENTER -->
            <!-- ===================================================== -->

            <section
                class="min-w-0 space-y-4"
            >
                <!-- Video -->
                <div
                    class="group relative overflow-hidden rounded-3xl border border-white/10 bg-black shadow-2xl"
                >
                    <video
                        v-if="videoUrl"
                        ref="video"
                        :src="videoUrl"
                        class="aspect-video w-full object-contain"
                        playsinline
                        @timeupdate="
                            handleTimeUpdate
                        "
                        @loadedmetadata="
                            handleLoadedMetadata
                        "
                        @play="handlePlay"
                        @pause="handlePause"
                    ></video>

                    <!-- Empty video -->
                    <div
                        v-else
                        class="flex aspect-video items-center justify-center bg-white/[0.02]"
                    >
                        <div
                            class="text-center"
                        >
                            <div
                                class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-white/5"
                            >
                                🎬
                            </div>

                            <p
                                class="text-sm text-white/35"
                            >
                                Loading media...
                            </p>
                        </div>
                    </div>

                    <!-- Subtitle Overlay -->
                    <div
                        v-if="activeSubtitle"
                        class="pointer-events-none absolute bottom-8 left-1/2 w-[90%] -translate-x-1/2 text-center"
                    >
                        <span
                            class="inline-block max-w-[90%] rounded-xl bg-black/75 px-4 py-2 text-base font-semibold leading-relaxed text-white shadow-2xl backdrop-blur-md md:text-xl lg:text-2xl"
                        >
                            {{
                                activeSubtitle.text
                            }}
                        </span>
                    </div>

                    <!-- Playing indicator -->
                    <div
                        v-if="isPlaying"
                        class="absolute left-4 top-4 rounded-full border border-white/10 bg-black/40 px-3 py-1.5 text-[10px] text-white/60 backdrop-blur-md"
                    >
                        ● Playing
                    </div>
                </div>

                <!-- Video Controls -->
                <div
                    class="rounded-2xl border border-white/10 bg-white/[0.035] p-4 backdrop-blur-xl"
                >
                    <div
                        class="flex items-center gap-3 md:gap-4"
                    >
                        <button
                            type="button"
                            @click="togglePlay"
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10 text-sm transition hover:bg-white/15"
                        >
                            <span
                                v-if="!isPlaying"
                            >
                                ▶
                            </span>

                            <span v-else>
                                ❚❚
                            </span>
                        </button>

                        <div
                            class="w-[90px] shrink-0 text-xs tabular-nums text-white/40"
                        >
                            {{
                                formatTime(
                                    currentTime
                                )
                            }}

                            <span
                                class="text-white/20"
                            >
                                /
                            </span>

                            {{
                                formatTime(
                                    duration
                                )
                            }}
                        </div>

                        <!-- Progress -->
                        <div
                            class="relative h-2 flex-1 cursor-pointer overflow-hidden rounded-full bg-white/10"
                            @click="
                                handleTimelineClick
                            "
                        >
                            <div
                                class="absolute inset-y-0 left-0 rounded-full bg-cyan-400 transition-[width]"
                                :style="{
                                    width:
                                        timelinePosition +
                                        '%',
                                }"
                            ></div>
                        </div>
                    </div>
                </div>

                <!-- Timeline -->
                <div
                    class="overflow-hidden rounded-3xl border border-white/10 bg-white/[0.035] p-4 backdrop-blur-xl"
                >
                    <div
                        class="mb-4 flex items-center justify-between gap-3"
                    >
                        <div>
                            <p
                                class="text-sm font-semibold"
                            >
                                Timeline
                            </p>

                            <p
                                class="mt-1 text-xs text-white/35"
                            >
                                {{
                                    localSubtitles.length
                                }}
                                subtitle segments
                            </p>
                        </div>

                        <button
                            type="button"
                            @click="addSubtitle"
                            class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs transition hover:bg-white/10"
                        >
                            + Add Subtitle
                        </button>
                    </div>

                    <div
                        class="relative overflow-x-auto rounded-2xl bg-black/20"
                    >
                        <div
                            class="relative h-24 min-w-[800px]"
                        >
                            <!-- Track -->
                            <div
                                class="absolute left-0 right-0 top-1/2 h-px bg-white/10"
                            ></div>

                            <!-- Subtitle Blocks -->
                            <button
                                v-for="subtitle in localSubtitles"
                                :key="
                                    subtitle.id ??
                                    subtitle.sequence
                                "
                                type="button"
                                @click="
                                    selectSubtitle(
                                        subtitle
                                    )
                                "
                                class="absolute top-6 h-12 overflow-hidden rounded-xl border px-2 text-left text-[10px] transition"
                                :class="
                                    (
                                        selectedId ===
                                        (
                                            subtitle.id ??
                                            subtitle.sequence
                                        )
                                    )
                                        ? 'border-cyan-300/60 bg-cyan-400/20 shadow-lg shadow-cyan-400/5'
                                        : 'border-white/10 bg-white/10 hover:bg-white/15'
                                "
                                :style="{
                                    left:
                                        duration
                                            ? (
                                                  Number(
                                                      subtitle.start_time
                                                  ) /
                                                      duration
                                              ) *
                                                  100 +
                                              '%'
                                            : '0%',

                                    width:
                                        duration
                                            ? Math.max(
                                                  4,
                                                  (
                                                      (
                                                          Number(
                                                              subtitle.end_time
                                                          ) -
                                                          Number(
                                                              subtitle.start_time
                                                          )
                                                      ) /
                                                          duration
                                                  ) *
                                                      100
                                              ) +
                                              '%'
                                            : '10%',
                                }"
                            >
                                <span
                                    class="block truncate font-medium"
                                >
                                    {{
                                        subtitle.text
                                    }}
                                </span>

                                <span
                                    class="mt-1 block truncate text-[9px] text-white/30"
                                >
                                    {{
                                        formatTime(
                                            subtitle.start_time
                                        )
                                    }}
                                </span>
                            </button>

                            <!-- Current time line -->
                            <div
                                class="pointer-events-none absolute bottom-0 top-0 z-20 w-px bg-cyan-300 shadow-[0_0_12px_rgba(103,232,249,0.7)]"
                                :style="{
                                    left:
                                        timelinePosition +
                                        '%',
                                }"
                            ></div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ===================================================== -->
            <!-- RIGHT EDITOR -->
            <!-- ===================================================== -->

            <aside
                class="rounded-3xl border border-white/10 bg-white/[0.035] p-4 backdrop-blur-xl"
            >
                <!-- Header -->
                <div
                    class="mb-4 flex items-center justify-between"
                >
                    <div>
                        <p
                            class="text-sm font-semibold"
                        >
                            Subtitles
                        </p>

                        <p
                            class="mt-1 text-xs text-white/35"
                        >
                            Edit generated captions
                        </p>
                    </div>

                    <span
                        class="rounded-full border border-white/10 bg-white/5 px-2.5 py-1 text-[10px] text-white/40"
                    >
                        {{
                            localSubtitles.length
                        }}
                    </span>
                </div>

                <!-- Search -->
                <div
                    class="relative mb-4"
                >
                    <span
                        class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-white/25"
                    >
                        ⌕
                    </span>

                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search subtitles..."
                        class="w-full rounded-xl border border-white/10 bg-black/20 py-2.5 pl-8 pr-3 text-sm outline-none transition placeholder:text-white/25 focus:border-cyan-300/30"
                    />
                </div>

                <!-- Subtitle List -->
                <div
                    class="mb-5 max-h-[280px] space-y-2 overflow-y-auto pr-1"
                >
                    <button
                        v-for="subtitle in filteredSubtitles"
                        :key="
                            subtitle.id ??
                            subtitle.sequence
                        "
                        type="button"
                        @click="
                            selectSubtitle(
                                subtitle
                            )
                        "
                        class="w-full rounded-2xl border p-3 text-left transition"
                        :class="
                            selectedId ===
                            (
                                subtitle.id ??
                                subtitle.sequence
                            )
                                ? 'border-cyan-300/30 bg-cyan-400/10'
                                : 'border-white/5 bg-white/[0.025] hover:bg-white/5'
                        "
                    >
                        <div
                            class="mb-1 flex items-center justify-between text-[10px] text-white/30"
                        >
                            <span>
                                #
                                {{
                                    subtitle.sequence
                                }}
                            </span>

                            <span>
                                {{
                                    formatTime(
                                        subtitle.start_time
                                    )
                                }}
                            </span>
                        </div>

                        <p
                            class="line-clamp-2 text-xs leading-5 text-white/75"
                        >
                            {{
                                subtitle.text
                            }}
                        </p>
                    </button>

                    <!-- Empty -->
                    <div
                        v-if="
                            !filteredSubtitles.length
                        "
                        class="rounded-2xl border border-dashed border-white/10 p-6 text-center"
                    >
                        <p
                            class="text-xs text-white/30"
                        >
                            No subtitles found.
                        </p>
                    </div>
                </div>

                <!-- ================================================= -->
                <!-- EDITOR -->
                <!-- ================================================= -->

                <div
                    v-if="selectedSubtitle"
                    class="border-t border-white/10 pt-5"
                >
                    <div
                        class="mb-4 flex items-center justify-between"
                    >
                        <div>
                            <p
                                class="text-xs font-semibold uppercase tracking-wider text-white/40"
                            >
                                Edit Segment
                            </p>

                            <p
                                class="mt-1 text-[10px] text-white/25"
                            >
                                Segment #
                                {{
                                    selectedSubtitle.sequence
                                }}
                            </p>
                        </div>

                        <button
                            type="button"
                            @click="
                                deleteSubtitle
                            "
                            class="text-xs text-red-300/60 transition hover:text-red-300"
                        >
                            Delete
                        </button>
                    </div>

                    <!-- Timing -->
                    <div
                        class="grid grid-cols-2 gap-2"
                    >
                        <label>
                            <span
                                class="mb-1 block text-[10px] text-white/35"
                            >
                                Start
                            </span>

                            <input
                                :value="
                                    selectedSubtitle.start_time
                                "
                                @input="
                                    updateSelected(
                                        'start_time',
                                        Number(
                                            $event
                                                .target
                                                .value
                                        )
                                    )
                                "
                                type="number"
                                min="0"
                                step="0.001"
                                class="w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2.5 text-xs outline-none transition focus:border-cyan-300/30"
                            />
                        </label>

                        <label>
                            <span
                                class="mb-1 block text-[10px] text-white/35"
                            >
                                End
                            </span>

                            <input
                                :value="
                                    selectedSubtitle.end_time
                                "
                                @input="
                                    updateSelected(
                                        'end_time',
                                        Number(
                                            $event
                                                .target
                                                .value
                                        )
                                    )
                                "
                                type="number"
                                min="0"
                                step="0.001"
                                class="w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2.5 text-xs outline-none transition focus:border-cyan-300/30"
                            />
                        </label>
                    </div>

                    <!-- Text -->
                    <label
                        class="mt-3 block"
                    >
                        <span
                            class="mb-1 block text-[10px] text-white/35"
                        >
                            Subtitle Text
                        </span>

                        <textarea
                            :value="
                                selectedSubtitle.text
                            "
                            @input="
                                updateSelected(
                                    'text',
                                    $event
                                        .target
                                        .value
                                )
                            "
                            rows="5"
                            maxlength="1000"
                            class="w-full resize-none rounded-2xl border border-white/10 bg-black/20 p-3 text-sm leading-6 outline-none transition placeholder:text-white/20 focus:border-cyan-300/30"
                            placeholder="Enter subtitle text..."
                        ></textarea>
                    </label>

                    <!-- ================================================= -->
                    <!-- AI ASSIST BUTTON -->
                    <!-- ================================================= -->

                    <div
                        class="mt-4"
                    >
                        <button
                            type="button"
                            @click="
                                aiOpen =
                                    !aiOpen
                            "
                            class="flex w-full items-center justify-center gap-2 rounded-xl border border-cyan-300/20 bg-cyan-400/10 px-4 py-3 text-xs font-semibold text-cyan-200 transition hover:bg-cyan-400/15"
                        >
                            <span>
                                ✨
                            </span>

                            <span>
                                AI Assist
                            </span>

                            <span
                                class="ml-1 text-cyan-300/40"
                            >
                                {{
                                    aiOpen
                                        ? '↑'
                                        : '↓'
                                }}
                            </span>
                        </button>
                    </div>

                    <!-- ================================================= -->
                    <!-- AI ASSIST PANEL -->
                    <!-- ================================================= -->

                    <div
                        v-if="aiOpen"
                        class="mt-3 rounded-2xl border border-cyan-300/10 bg-cyan-400/[0.035] p-4"
                    >
                        <!-- AI Header -->
                        <div
                            class="mb-4 flex items-start justify-between gap-3"
                        >
                            <div>
                                <p
                                    class="text-sm font-semibold"
                                >
                                    AI Assistant
                                </p>

                                <p
                                    class="mt-1 text-[11px] leading-5 text-white/35"
                                >
                                    Improve your subtitle
                                    automatically.
                                </p>
                            </div>

                            <button
                                type="button"
                                @click="
                                    closeAIAssistant
                                "
                                class="text-lg leading-none text-white/30 transition hover:text-white"
                            >
                                ×
                            </button>
                        </div>

                        <!-- Actions -->
                        <div
                            class="grid grid-cols-2 gap-2"
                        >
                            <button
                                type="button"
                                @click="
                                    runAIAssist(
                                        'improve'
                                    )
                                "
                                :disabled="
                                    aiLoading
                                "
                                class="rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-xs transition hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-40"
                            >
                                ✨ Improve
                            </button>

                            <button
                                type="button"
                                @click="
                                    runAIAssist(
                                        'grammar'
                                    )
                                "
                                :disabled="
                                    aiLoading
                                "
                                class="rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-xs transition hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-40"
                            >
                                📝 Grammar
                            </button>

                            <button
                                type="button"
                                @click="
                                    runAIAssist(
                                        'shorten'
                                    )
                                "
                                :disabled="
                                    aiLoading
                                "
                                class="rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-xs transition hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-40"
                            >
                                ✂️ Shorten
                            </button>

                            <button
                                type="button"
                                @click="
                                    runAIAssist(
                                        'translate',
                                        'en'
                                    )
                                "
                                :disabled="
                                    aiLoading
                                "
                                class="rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-xs transition hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-40"
                            >
                                🇬🇧 English
                            </button>

                            <button
                                type="button"
                                @click="
                                    runAIAssist(
                                        'translate',
                                        'bn'
                                    )
                                "
                                :disabled="
                                    aiLoading
                                "
                                class="col-span-2 rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-xs transition hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-40"
                            >
                                🇧🇩 Bangla
                            </button>
                        </div>

                        <!-- Loading -->
                        <div
                            v-if="aiLoading"
                            class="mt-4 rounded-xl border border-white/5 bg-black/20 p-3"
                        >
                            <div
                                class="flex items-center gap-3"
                            >
                                <div
                                    class="h-4 w-4 animate-spin rounded-full border-2 border-white/10 border-t-cyan-300"
                                ></div>

                                <div>
                                    <p
                                        class="text-xs font-medium"
                                    >
                                        AI is working...
                                    </p>

                                    <p
                                        class="mt-1 text-[10px] text-white/30"
                                    >
                                        {{
                                            aiAction
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Error -->
                        <div
                            v-if="aiError"
                            class="mt-4 rounded-xl border border-red-400/10 bg-red-500/10 p-3"
                        >
                            <p
                                class="text-xs leading-5 text-red-300"
                            >
                                {{
                                    aiError
                                }}
                            </p>
                        </div>

                        <!-- Result -->
                        <div
                            v-if="
                                aiResult &&
                                !aiLoading
                            "
                            class="mt-4"
                        >
                            <div
                                class="mb-2 flex items-center justify-between"
                            >
                                <p
                                    class="text-[10px] font-semibold uppercase tracking-wider text-white/30"
                                >
                                    AI Result
                                </p>

                                <span
                                    class="text-[10px] text-cyan-300/40"
                                >
                                    Preview
                                </span>
                            </div>

                            <div
                                class="rounded-xl border border-cyan-300/10 bg-black/20 p-3"
                            >
                                <p
                                    class="text-sm leading-6 text-white/80"
                                >
                                    {{
                                        aiResult
                                    }}
                                </p>
                            </div>

                            <div
                                class="mt-3 grid grid-cols-2 gap-2"
                            >
                                <button
                                    type="button"
                                    @click="
                                        applyAIResult
                                    "
                                    class="rounded-xl bg-cyan-400 px-3 py-2.5 text-xs font-semibold text-black transition hover:bg-cyan-300"
                                >
                                    Apply
                                </button>

                                <button
                                    type="button"
                                    @click="
                                        aiResult =
                                            ''
                                    "
                                    class="rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-xs transition hover:bg-white/10"
                                >
                                    Discard
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- ================================================= -->
                    <!-- Editor Actions -->
                    <!-- ================================================= -->

                    <div
                        class="mt-4 grid grid-cols-2 gap-2"
                    >
                        <button
                            type="button"
                            @click="
                                duplicateSubtitle
                            "
                            class="rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-xs transition hover:bg-white/10"
                        >
                            Duplicate
                        </button>

                        <button
                            type="button"
                            @click="save"
                            :disabled="saving"
                            class="rounded-xl bg-cyan-400 px-3 py-2.5 text-xs font-semibold text-black transition hover:bg-cyan-300 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{
                                saving
                                    ? 'Saving...'
                                    : 'Save Changes'
                            }}
                        </button>
                    </div>
                </div>

                <!-- No selection -->
                <div
                    v-else
                    class="rounded-2xl border border-dashed border-white/10 p-8 text-center"
                >
                    <div
                        class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-white/5"
                    >
                        ✏️
                    </div>

                    <p
                        class="text-xs text-white/30"
                    >
                        Select a subtitle segment
                        to start editing.
                    </p>
                </div>
            </aside>
        </main>

        <!-- ========================================================= -->
        <!-- Mobile Bottom Actions -->
        <!-- ========================================================= -->

        <div
            class="sticky bottom-0 z-30 border-t border-white/10 bg-[#070812]/80 p-3 backdrop-blur-xl xl:hidden"
        >
            <div
                class="flex gap-2"
            >
                <button
                    type="button"
                    @click="emit('new-project')"
                    class="flex-1 cursor-pointer rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-xs transition hover:bg-white/10"
                >
                    New Project
                </button>

                <button
                    type="button"
                    @click="emit('logout')"
                    class="cursor-pointer rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-xs text-white/60 transition hover:bg-white/10 hover:text-white"
                >
                    Logout
                </button>
            </div>
            <div
    v-if="selectedSubtitle"
    class="mt-4 rounded-2xl border border-white/10 bg-black/20 p-4"
>
    <div
        class="mb-3 flex items-center justify-between"
    >
        <div>
            <p
                class="text-xs font-semibold"
            >
                Subtitle Quality
            </p>

            <p
                class="mt-1 text-[10px] text-white/30"
            >
                Professional readability check
            </p>
        </div>

        <span
            class="rounded-full px-2.5 py-1 text-[10px] font-medium"
            :class="{
                'bg-emerald-400/10 text-emerald-300':
                    selectedSubtitle.quality === 'good',

                'bg-amber-400/10 text-amber-300':
                    selectedSubtitle.quality === 'warning',

                'bg-red-400/10 text-red-300':
                    selectedSubtitle.quality === 'poor',
            }"
        >
            {{
                selectedSubtitle.quality === 'good'
                    ? 'Good'
                    : selectedSubtitle.quality === 'warning'
                        ? 'Review'
                        : 'Poor'
            }}
        </span>
    </div>

    <div
        class="grid grid-cols-3 gap-2"
    >
        <div
            class="rounded-xl border border-white/5 bg-white/[0.025] p-3"
        >
            <p
                class="text-sm font-semibold"
            >
                {{
                    selectedSubtitle.characters ??
                    selectedSubtitle.text?.length ??
                    0
                }}
            </p>

            <p
                class="mt-1 text-[9px] text-white/30"
            >
                Characters
            </p>
        </div>

        <div
            class="rounded-xl border border-white/5 bg-white/[0.025] p-3"
        >
            <p
                class="text-sm font-semibold"
            >
                {{
                    selectedSubtitle.words ??
                    selectedSubtitle.text
                        ?.trim()
                        .split(/\s+/)
                        .length ??
                    0
                }}
            </p>

            <p
                class="mt-1 text-[9px] text-white/30"
            >
                Words
            </p>
        </div>

        <div
            class="rounded-xl border border-white/5 bg-white/[0.025] p-3"
        >
            <p
                class="text-sm font-semibold"
            >
                {{
                    Number(
                        selectedSubtitle.cps || 0
                    ).toFixed(1)
                }}
            </p>

            <p
                class="mt-1 text-[9px] text-white/30"
            >
                CPS
            </p>
        </div>
    </div>

    <div
        class="mt-3 space-y-1.5"
    >
        <div
            class="flex items-center justify-between text-[10px]"
        >
            <span class="text-white/30">
                Character limit
            </span>

            <span
                :class="
                    (
                        selectedSubtitle.characters ??
                        selectedSubtitle.text?.length ??
                        0
                    ) <= 42
                        ? 'text-emerald-300'
                        : 'text-red-300'
                "
            >
                {{
                    selectedSubtitle.characters ??
                    selectedSubtitle.text?.length ??
                    0
                }}/42
            </span>
        </div>

        <div
            class="flex items-center justify-between text-[10px]"
        >
            <span class="text-white/30">
                Reading speed
            </span>

            <span
                :class="
                    Number(
                        selectedSubtitle.cps || 0
                    ) <= 20
                        ? 'text-emerald-300'
                        : 'text-red-300'
                "
            >
                {{
                    Number(
                        selectedSubtitle.cps || 0
                    ).toFixed(1)
                }}
                CPS
            </span>
        </div>

        <div
            class="flex items-center justify-between text-[10px]"
        >
            <span class="text-white/30">
                Lines
            </span>

            <span
                :class="
                    (
                        selectedSubtitle.lines?.length ||
                        1
                    ) <= 2
                        ? 'text-emerald-300'
                        : 'text-red-300'
                "
            >
                {{
                    selectedSubtitle.lines?.length ||
                    1
                }}/2
            </span>
        </div>
    </div>
</div>
        </div>
    </div>
</template>