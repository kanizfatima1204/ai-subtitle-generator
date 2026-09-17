<script setup>
import { onMounted, onUnmounted, ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

import api from '../services/api';
import FileUploader from '../Components/FileUploader.vue';
import SubtitleWorkspace from '../Components/SubtitleWorkspace.vue';


const job = ref(null);
const subtitles = ref([]);

const loading = ref(false);
const uploading = ref(false);
const saving = ref(false);

const error = ref('');
const success = ref('');

const polling = ref(false);
const pollTimer = ref(null);

const showUploader = ref(false);

const selectedLanguage = ref('auto');
const selectedModel = ref('base');

const user = ref(
    JSON.parse(localStorage.getItem('auth_user') || 'null')
);

/*
|--------------------------------------------------------------------------
| Computed
|--------------------------------------------------------------------------
*/

const hasJob = computed(() => {
    return !!job.value;
});

const isProcessing = computed(() => {
    return (
        job.value &&
        ['pending', 'processing'].includes(job.value.status)
    );
});

const isCompleted = computed(() => {
    return job.value?.status === 'completed';
});

const isFailed = computed(() => {
    return job.value?.status === 'failed';
});

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

async function checkAuthentication() {
    const token = localStorage.getItem('auth_token');

    if (!token) {
        router.visit('/login');
        return false;
    }

    try {
        const response = await api.get('/api/auth/me');

        user.value = response.data.user;

        localStorage.setItem(
            'auth_user',
            JSON.stringify(response.data.user)
        );

        return true;
    } catch (err) {
        logout(false);
        return false;
    }
}

/*
|--------------------------------------------------------------------------
| Upload
|--------------------------------------------------------------------------
*/

async function handleUpload(file) {
    if (!file) {
        return;
    }

    error.value = '';
    success.value = '';
    uploading.value = true;

    try {
        const formData = new FormData();

        formData.append('file', file);

        formData.append(
            'language',
            selectedLanguage.value
        );

        formData.append(
            'model',
            selectedModel.value
        );

        const response = await api.post(
            '/api/subtitle-jobs',
            formData,
            {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },

                timeout: 120000,
            }
        );

        job.value = response.data.job;

        subtitles.value = [];

        showUploader.value = false;

        success.value =
            'Video uploaded successfully. AI processing has started.';

        startPolling(job.value.id);
    } catch (err) {
        console.error(err);

        error.value =
            err.response?.data?.message ||
            'Unable to upload the media file.';
    } finally {
        uploading.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Poll Job
|--------------------------------------------------------------------------
*/

function startPolling(jobId) {
    stopPolling();

    polling.value = true;

    pollTimer.value = setInterval(() => {
        checkJobStatus(jobId);
    }, 2000);

    checkJobStatus(jobId);
}

async function checkJobStatus(jobId) {
    try {
        const response = await api.get(
            `/api/subtitle-jobs/${jobId}`
        );

        job.value = response.data;

        if (job.value.status === 'completed') {
            stopPolling();

            success.value =
                'Subtitles generated successfully.';

            await loadSubtitles(jobId);
        }

        if (job.value.status === 'failed') {
            stopPolling();

            error.value =
                job.value.error_message ||
                'Subtitle processing failed.';
        }
    } catch (err) {
        console.error(
            'Polling error:',
            err
        );
    }
}

/*
|--------------------------------------------------------------------------
| Load Subtitles
|--------------------------------------------------------------------------
*/

async function loadSubtitles(jobId) {
    loading.value = true;

    try {
        const response = await api.get(
            `/api/subtitle-jobs/${jobId}/preview`
        );

        subtitles.value =
            response.data.subtitles || [];
    } catch (err) {
        console.error(err);

        error.value =
            err.response?.data?.message ||
            'Unable to load subtitles.';
    } finally {
        loading.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Save Subtitles
|--------------------------------------------------------------------------
*/

async function saveSubtitles() {
    if (!job.value) {
        return;
    }

    if (!subtitles.value.length) {
        error.value =
            'There are no subtitles to save.';

        return;
    }

    error.value = '';
    success.value = '';
    saving.value = true;

    try {
        const response = await api.put(
            `/api/subtitle-jobs/${job.value.id}/subtitles`,
            {
                subtitles:
                    subtitles.value.map(
                        (subtitle) => ({
                            id: subtitle.id || null,

                            start:
                                Number(
                                    subtitle.start_time ??
                                    subtitle.start
                                ),

                            end:
                                Number(
                                    subtitle.end_time ??
                                    subtitle.end
                                ),

                            text:
                                subtitle.text || '',
                        })
                    ),
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Reload server version
        |--------------------------------------------------------------------------
        */

        subtitles.value =
            response.data.subtitles ||
            subtitles.value;

        success.value =
            response.data.message ||
            'Subtitles saved and SRT regenerated.';
    } catch (err) {
        console.error(err);

        error.value =
            err.response?.data?.message ||
            extractValidationError(err) ||
            'Unable to save subtitles.';
    } finally {
        saving.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Validation Error Helper
|--------------------------------------------------------------------------
*/

function extractValidationError(err) {
    const errors =
        err.response?.data?.errors;

    if (!errors) {
        return '';
    }

    const firstError = Object.values(errors)
        .flat()
        .find(Boolean);

    return firstError || '';
}

/*
|--------------------------------------------------------------------------
| Add Subtitle
|--------------------------------------------------------------------------
*/

function addSubtitle() {
    const lastSubtitle =
        subtitles.value[
            subtitles.value.length - 1
        ];

    let start = 0;

    if (lastSubtitle) {
        start =
            Number(
                lastSubtitle.end_time ??
                lastSubtitle.end
            ) + 0.1;
    }

    const newSubtitle = {
        id: null,

        sequence:
            subtitles.value.length + 1,

        start_time: Number(
            start.toFixed(3)
        ),

        end_time: Number(
            (start + 2).toFixed(3)
        ),

        text: 'New subtitle...',
    };

    subtitles.value.push(
        newSubtitle
    );
}

/*
|--------------------------------------------------------------------------
| Delete Subtitle
|--------------------------------------------------------------------------
*/

function deleteSubtitle(subtitle) {
    const index =
        subtitles.value.findIndex(
            (item) =>
                item === subtitle ||
                (
                    item.id &&
                    item.id === subtitle.id
                )
        );

    if (index === -1) {
        return;
    }

    subtitles.value.splice(
        index,
        1
    );

    normalizeSequences();

    success.value =
        'Subtitle removed. Click Save to apply the changes.';
}

/*
|--------------------------------------------------------------------------
| Duplicate Subtitle
|--------------------------------------------------------------------------
*/

function duplicateSubtitle(subtitle) {
    const index =
        subtitles.value.findIndex(
            (item) =>
                item === subtitle ||
                (
                    item.id &&
                    item.id === subtitle.id
                )
        );

    if (index === -1) {
        return;
    }

    const start =
        Number(
            subtitle.end_time ??
            subtitle.end
        ) + 0.1;

    const end =
        start +
        (
            Number(
                subtitle.end_time ??
                subtitle.end
            ) -
            Number(
                subtitle.start_time ??
                subtitle.start
            )
        );

    const duplicate = {
        id: null,

        sequence:
            subtitles.value.length + 1,

        start_time:
            Number(start.toFixed(3)),

        end_time:
            Number(end.toFixed(3)),

        text: subtitle.text,
    };

    subtitles.value.splice(
        index + 1,
        0,
        duplicate
    );

    normalizeSequences();

    success.value =
        'Subtitle duplicated. Click Save to apply the changes.';
}

/*
|--------------------------------------------------------------------------
| Normalize Sequence
|--------------------------------------------------------------------------
*/

function normalizeSequences() {
    subtitles.value =
        subtitles.value.map(
            (subtitle, index) => ({
                ...subtitle,
                sequence: index + 1,
            })
        );
}

/*
|--------------------------------------------------------------------------
| Download SRT
|--------------------------------------------------------------------------
*/

async function downloadSrt() {
    if (!job.value) {
        return;
    }

    try {
        const response = await api.get(
            `/api/subtitle-jobs/${job.value.id}/download`,
            {
                responseType: 'blob',
            }
        );

        const blob =
            new Blob(
                [response.data],
                {
                    type: 'application/x-subrip',
                }
            );

        const url =
            window.URL.createObjectURL(
                blob
            );

        const link =
            document.createElement('a');

        link.href = url;

        link.download =
            getSrtFilename();

        document.body.appendChild(link);

        link.click();

        link.remove();

        window.URL.revokeObjectURL(
            url
        );
    } catch (err) {
        console.error(err);

        error.value =
            'Unable to download the SRT file.';
    }
}

function getSrtFilename() {
    const filename =
        job.value?.original_filename ||
        'subtitles';

    const cleanName =
        filename
            .replace(/\.[^/.]+$/, '')
            .replace(
                /[^a-zA-Z0-9-_]+/g,
                '-'
            );

    return `${cleanName}.srt`;
}

/*
|--------------------------------------------------------------------------
| New Project
|--------------------------------------------------------------------------
*/

function createNewProject() {
    stopPolling();

    job.value = null;

    subtitles.value = [];

    error.value = '';

    success.value = '';

    showUploader.value = true;

    if (typeof window !== 'undefined' && window.history?.pushState) {
        const url = new URL(window.location.href);
        if (url.searchParams.has('job')) {
            url.searchParams.delete('job');
            window.history.pushState({}, '', url.pathname);
        }
    }
}

/*
|--------------------------------------------------------------------------
| Logout
|--------------------------------------------------------------------------
*/

async function logout(redirect = true) {
    stopPolling();

    try {
        await api.post(
            '/api/auth/logout'
        );
    } catch (err) {
        console.error(
            'Logout request failed:',
            err
        );
    }

    localStorage.removeItem(
        'auth_token'
    );

    localStorage.removeItem(
        'auth_user'
    );

    user.value = null;

    if (redirect) {
        router.visit('/login');
    }
}

/*
|--------------------------------------------------------------------------
| Video URL
|--------------------------------------------------------------------------
|
| For the current local MVP, uploaded files are
| stored on Laravel's public disk.
|
| Example:
| storage/app/public/subtitle-inputs/example.mp4
|
| becomes:
| /storage/subtitle-inputs/example.mp4
|
*/

const videoUrl = computed(() => {
    if (!job.value?.id) {
        return '';
    }

    const token = localStorage.getItem('auth_token');
    return `/api/subtitle-jobs/${job.value.id}/media` + (token ? `?token=${encodeURIComponent(token)}` : '');
});

/*
|--------------------------------------------------------------------------
| Job From URL
|--------------------------------------------------------------------------
*/

async function loadJobFromUrl() {
    const params =
        new URLSearchParams(
            window.location.search
        );

    const jobId =
        params.get('job');

    if (!jobId) {
        return;
    }

    loading.value = true;

    try {
        const response = await api.get(
            `/api/subtitle-jobs/${jobId}`
        );

        job.value = response.data;

        if (
            job.value.status ===
            'completed'
        ) {
            await loadSubtitles(
                job.value.id
            );
        } else if (
            [
                'pending',
                'processing',
            ].includes(
                job.value.status
            )
        ) {
            startPolling(
                job.value.id
            );
        }
    } catch (err) {
        console.error(err);

        error.value =
            err.response?.data?.message ||
            'Unable to load this project.';
    } finally {
        loading.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Lifecycle
|--------------------------------------------------------------------------
*/

onMounted(async () => {
    const authenticated =
        await checkAuthentication();

    if (!authenticated) {
        return;
    }

    await loadJobFromUrl();
});

onUnmounted(() => {
    stopPolling();
});

function stopPolling() {
    polling.value = false;

    if (pollTimer.value) {
        clearInterval(
            pollTimer.value
        );

        pollTimer.value = null;
    }
}
</script>

<template>
    <div class="min-h-screen bg-[#080b14]">
        <!-- ========================================================= -->
        <!-- UPLOAD SCREEN                                             -->
        <!-- ========================================================= -->

        <div
            v-if="!hasJob || showUploader"
            class="relative min-h-screen overflow-hidden px-4 py-8 md:px-8"
        >
            <!-- Background glow -->

            <div
                class="pointer-events-none absolute -left-40 -top-40 h-[500px] w-[500px] rounded-full bg-indigo-600/20 blur-[140px]"
            ></div>

            <div
                class="pointer-events-none absolute -bottom-40 -right-40 h-[500px] w-[500px] rounded-full bg-purple-600/20 blur-[140px]"
            ></div>

            <div
                class="relative mx-auto max-w-6xl"
            >
                <!-- Header -->

                <header
                    class="mb-8 flex items-center justify-between rounded-3xl border border-white/10 bg-white/[0.06] px-5 py-4 backdrop-blur-2xl"
                >
                    <div
                        class="flex items-center gap-3"
                    >
                        <div
                            class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 text-lg font-bold shadow-lg shadow-indigo-500/20"
                        >
                            ✦
                        </div>

                        <div>
                            <h1
                                class="font-bold text-white"
                            >
                                AI Subtitle Studio
                            </h1>

                            <p
                                class="text-xs text-white/40"
                            >
                                Intelligent subtitle workspace
                            </p>
                        </div>
                    </div>

                    <div
                        class="flex items-center gap-3"
                    >
                        <a
                            href="/history"
                            class="hidden rounded-xl border border-white/10 bg-white/5 px-4 py-2 text-sm text-white/60 transition hover:bg-white/10 hover:text-white sm:block"
                        >
                            History
                        </a>

                        <button
                            @click="logout()"
                            class="rounded-xl border border-white/10 bg-white/5 px-4 py-2 text-sm text-white/60 transition hover:bg-white/10 hover:text-white"
                        >
                            Logout
                        </button>
                    </div>
                </header>

                <!-- Upload Card -->

                <div
                    class="mx-auto max-w-3xl rounded-[32px] border border-white/10 bg-white/[0.06] p-5 shadow-2xl backdrop-blur-2xl md:p-8"
                >
                    <div
                        class="mb-8 text-center"
                    >
                        <div
                            class="mx-auto mb-5 flex h-20 w-20 items-center justify-center rounded-3xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20 text-3xl"
                        >
                            ✦
                        </div>

                        <h2
                            class="text-2xl font-bold md:text-3xl"
                        >
                            Create AI Subtitles
                        </h2>

                        <p
                            class="mx-auto mt-3 max-w-xl text-sm leading-relaxed text-white/40"
                        >
                            Upload your video or audio and let
                            AI automatically generate accurate
                            subtitles with timestamps.
                        </p>
                    </div>

                    <!-- File Uploader -->

                    <FileUploader
                        @upload="handleUpload"
                    />

                    <!-- Settings -->

                    <div
                        class="mt-6 grid gap-4 md:grid-cols-2"
                    >
                        <!-- Language -->

                        <div>
                            <label
                                class="mb-2 block text-xs font-medium uppercase tracking-wider text-white/40"
                            >
                                Language
                            </label>

                            <select
                                v-model="
                                    selectedLanguage
                                "
                                class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm text-white outline-none focus:border-indigo-400/50"
                            >
                                <option
                                    value="auto"
                                    class="bg-[#101522]"
                                >
                                    Auto Detect
                                </option>

                                <option
                                    value="bn"
                                    class="bg-[#101522]"
                                >
                                    Bangla
                                </option>

                                <option
                                    value="en"
                                    class="bg-[#101522]"
                                >
                                    English
                                </option>

                                <option
                                    value="mixed"
                                    class="bg-[#101522]"
                                >
                                    Bangla + English
                                </option>
                            </select>
                        </div>

                        <!-- Model -->

                        <div>
                            <label
                                class="mb-2 block text-xs font-medium uppercase tracking-wider text-white/40"
                            >
                                AI Model
                            </label>

                            <select
                                v-model="
                                    selectedModel
                                "
                                class="w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm text-white outline-none focus:border-indigo-400/50"
                            >
                                <option
                                    value="base"
                                    class="bg-[#101522]"
                                >
                                    Base — Recommended (Fast & Cloud-Optimized)
                                </option>

                                <option
                                    value="tiny"
                                    class="bg-[#101522]"
                                >
                                    Tiny — Ultra Fast
                                </option>

                                <option
                                    value="small"
                                    class="bg-[#101522]"
                                >
                                    Small — Higher Accuracy
                                </option>

                                <option
                                    value="medium"
                                    class="bg-[#101522]"
                                >
                                    Medium — High Memory (4GB+ RAM)
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Uploading -->

                    <div
                        v-if="uploading"
                        class="mt-6 flex items-center gap-3 rounded-2xl border border-indigo-400/20 bg-indigo-500/10 p-4"
                    >
                        <div
                            class="h-5 w-5 animate-spin rounded-full border-2 border-white/20 border-t-indigo-400"
                        ></div>

                        <div>
                            <p
                                class="text-sm font-medium text-indigo-200"
                            >
                                Uploading media...
                            </p>

                            <p
                                class="text-xs text-white/40"
                            >
                                Please wait.
                            </p>
                        </div>
                    </div>

                    <!-- Error -->

                    <div
                        v-if="error"
                        class="mt-6 rounded-2xl border border-red-400/20 bg-red-500/10 p-4 text-sm text-red-300"
                    >
                        {{ error }}
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- WORKSPACE                                                 -->
        <!-- ========================================================= -->

        <div
            v-else
            class="relative"
        >
            <!-- Processing Banner -->

            <div
                v-if="isProcessing"
                class="fixed bottom-5 left-1/2 z-50 flex -translate-x-1/2 items-center gap-3 rounded-2xl border border-indigo-400/20 bg-[#111625]/90 px-5 py-3 shadow-2xl backdrop-blur-xl"
            >
                <div
                    class="h-4 w-4 animate-spin rounded-full border-2 border-white/20 border-t-indigo-400"
                ></div>

                <div>
                    <p
                        class="text-sm font-medium"
                    >
                        AI is processing your subtitles
                    </p>

                    <p
                        class="text-xs text-white/40"
                    >
                        This may take a few moments...
                    </p>
                </div>
            </div>

            <!-- Error -->

            <div
                v-if="error"
                class="fixed right-5 top-5 z-[60] max-w-md rounded-2xl border border-red-400/20 bg-red-500/10 px-5 py-4 text-sm text-red-300 shadow-2xl backdrop-blur-xl"
            >
                <div
                    class="flex items-start gap-3"
                >
                    <span>⚠</span>

                    <div class="flex-1">
                        {{ error }}
                    </div>

                    <button
                        @click="error = ''"
                        class="text-white/40 hover:text-white"
                    >
                        ×
                    </button>
                </div>
            </div>

            <!-- Success -->

            <div
                v-if="success"
                class="fixed right-5 top-5 z-[60] max-w-md rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-300 shadow-2xl backdrop-blur-xl"
            >
                <div
                    class="flex items-start gap-3"
                >
                    <span>✓</span>

                    <div class="flex-1">
                        {{ success }}
                    </div>

                    <button
                        @click="success = ''"
                        class="text-white/40 hover:text-white"
                    >
                        ×
                    </button>
                </div>
            </div>

            <!-- Workspace -->

            <SubtitleWorkspace
                :job="job"
                :subtitles="subtitles"
                :video-url="videoUrl"
                @save="saveSubtitles"
                @download="downloadSrt"
                @add-subtitle="addSubtitle"
                @delete-subtitle="deleteSubtitle"
                @duplicate-subtitle="duplicateSubtitle"
                @new-project="createNewProject"
                @logout="logout"
            />
        </div>
    </div>
</template>
