<script setup>
import { onMounted, onUnmounted, ref, computed, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';

import api from '../services/api';
import FileUploader from '../Components/FileUploader.vue';
import SubtitleWorkspace from '../Components/SubtitleWorkspace.vue';
import LanguagePicker from '../Components/LanguagePicker.vue';


const job = ref(null);
const subtitles = ref([]);

const loading = ref(false);
const uploading = ref(false);
const pickedFile = ref(null);
const previewUrl = ref('');
const saving = ref(false);

const error = ref('');
const success = ref('');

const polling = ref(false);
const pollTimer = ref(null);

const showUploader = ref(false);
const uploaderCard = ref(null);

const selectedLanguage = ref('auto');
const selectedModel = ref('small');

const languages = [
    { code: 'auto', name: 'Auto detect', native: 'Auto detect' },
    { code: 'en', name: 'English', native: 'English' },
    { code: 'bn', name: 'Bengali', native: 'বাংলা' },
    { code: 'hi', name: 'Hindi', native: 'हिन्दी' },
    { code: 'ur', name: 'Urdu', native: 'اردو' },
    { code: 'ne', name: 'Nepali', native: 'नेपाली' },
    { code: 'ta', name: 'Tamil', native: 'தமிழ்' },
    { code: 'te', name: 'Telugu', native: 'తెలుగు' },
    { code: 'ml', name: 'Malayalam', native: 'മലയാളം' },
    { code: 'gu', name: 'Gujarati', native: 'ગુજરાતી' },
    { code: 'pa', name: 'Punjabi', native: 'ਪੰਜਾਬੀ' },
    { code: 'mr', name: 'Marathi', native: 'मराठी' },
    { code: 'kn', name: 'Kannada', native: 'ಕನ್ನಡ' },
    { code: 'as', name: 'Assamese', native: 'অসমীয়া' },
    { code: 'ks', name: 'Kashmiri', native: 'कॉशुर' },
    { code: 'or', name: 'Odia', native: 'ଓଡ଼ିଆ' },
    { code: 'ps', name: 'Pashto', native: 'پښتو' },
    { code: 'ar', name: 'Arabic', native: 'العربية' },
    { code: 'fa', name: 'Persian', native: 'فارسی' },
    { code: 'id', name: 'Indonesian', native: 'Bahasa Indonesia' },
    { code: 'ms', name: 'Malay', native: 'Bahasa Melayu' },
    { code: 'zh', name: 'Chinese', native: '中文' },
    { code: 'ja', name: 'Japanese', native: '日本語' },
    { code: 'ko', name: 'Korean', native: '한국어' },
    { code: 'es', name: 'Spanish', native: 'Español' },
    { code: 'fr', name: 'French', native: 'Français' },
    { code: 'de', name: 'German', native: 'Deutsch' },
    { code: 'pt', name: 'Portuguese', native: 'Português' },
    { code: 'ru', name: 'Russian', native: 'Русский' },
    { code: 'tr', name: 'Turkish', native: 'Türkçe' },
    { code: 'it', name: 'Italian', native: 'Italiano' },
];

const selectedLanguageName = computed(() => {
    return languages.find((language) => language.code === selectedLanguage.value)?.name || 'Auto detect';
});

function openUploader() {
    showUploader.value = true;

    nextTick(() => {
        uploaderCard.value?.scrollIntoView({
            behavior: 'smooth',
            block: 'center',
        });
    });
}

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

    pickedFile.value = file;
    previewUrl.value = URL.createObjectURL(file);
}

async function startUpload() {
    const file = pickedFile.value;
    if (!file) return;

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
                timeout: 120000,
            }
        );

        job.value = response.data.job;
        pickedFile.value = null;
        URL.revokeObjectURL(previewUrl.value);
        previewUrl.value = '';

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
        <LanguagePicker
            v-if="pickedFile"
            :file="pickedFile"
            :preview-url="previewUrl"
            :languages="languages"
            v-model="selectedLanguage"
            :loading="uploading"
            @back="pickedFile = null; URL.revokeObjectURL(previewUrl); previewUrl = ''"
            @start="startUpload"
        />
        <!-- ========================================================= -->
        <!-- UPLOAD SCREEN                                             -->
        <!-- ========================================================= -->

        <div
            v-else-if="!hasJob || showUploader"
            class="relative min-h-screen overflow-hidden px-4 py-8 md:px-8"
        >
            <div class="hero-grid absolute inset-0 opacity-60"></div>
            <div class="pointer-events-none absolute inset-x-0 top-0 h-96 bg-gradient-to-b from-[#8b5cf6]/15 via-[#312e81]/5 to-transparent"></div>
            <div class="pointer-events-none absolute -left-48 top-20 h-[520px] w-[520px] rounded-full bg-[#7c3aed]/15 blur-[150px]"></div>
            <div class="pointer-events-none absolute -bottom-48 -right-32 h-[520px] w-[520px] rounded-full bg-[#fb7185]/10 blur-[150px]"></div>

            <div class="relative mx-auto max-w-6xl">
                <header
                    class="mx-auto mb-8 flex max-w-[1200px] items-center justify-between rounded-[24px] border border-white/10 bg-[#111321]/75 px-4 py-4 shadow-[0_20px_70px_rgba(5,5,20,0.65)] backdrop-blur-2xl md:px-6"
                >
                    <div class="flex items-center gap-3">
                        <div class="brand-mark flex h-11 w-11 items-center justify-center rounded-2xl border border-violet-300/20 bg-violet-400/10 text-base font-black text-violet-200 shadow-[0_0_22px_rgba(139,92,246,0.3)]">
                            S
                        </div>

                        <div class="text-left">
                            <h1 class="brand-word text-xl font-black leading-none tracking-[-0.04em] text-white md:text-2xl">
                                Subtitle Lab
                            </h1>
                            <p class="mt-1 text-[10px] uppercase tracking-[0.24em] text-violet-200/50">Turn speech into story</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <a
                            href="/history"
                            class="hidden rounded-xl border border-white/10 bg-white/[0.04] px-4 py-2.5 text-sm font-semibold text-white/65 transition hover:border-violet-300/30 hover:bg-violet-400/10 hover:text-white sm:inline-flex"
                        >
                            History
                        </a>

                        <button
                            @click="openUploader"
                            class="inline-flex items-center gap-2 rounded-xl border border-white/10 bg-white/[0.06] px-4 py-2.5 text-sm font-semibold text-white/80 transition hover:border-violet-300/30 hover:bg-violet-400/10 hover:text-white"
                        >
                            New project
                            <span class="text-lg leading-none text-violet-300">+</span>
                        </button>
                    </div>
                </header>

                <div class="mx-auto max-w-5xl pt-4 text-center">
                    <div class="mb-5 inline-flex items-center gap-2 rounded-full border border-violet-300/20 bg-violet-400/10 px-3 py-1.5 text-[10px] font-bold uppercase tracking-[0.24em] text-violet-200">
                        <span class="h-1.5 w-1.5 rounded-full bg-fuchsia-300 shadow-[0_0_10px_#f0abfc]"></span>
                        Your ideas, perfectly timed
                    </div>

                    <h2 class="hero-title mx-auto max-w-[940px] text-4xl font-black leading-[0.94] tracking-[-0.06em] text-white md:text-[6vw] md:leading-[0.9]">
                        Make every word<br />
                        <span class="hero-gradient">feel the moment.</span>
                    </h2>

                    <p class="mx-auto mt-6 max-w-[780px] text-base leading-relaxed text-white/55 md:text-[1.25rem]">
                        Generate clean, beautifully timed subtitles from any video or audio — then polish every line in one focused workspace.
                    </p>

                    <div class="mt-8 flex justify-center">
                        <button
                            class="hero-cta inline-flex items-center gap-3 rounded-2xl bg-gradient-to-r from-violet-500 to-fuchsia-500 px-8 py-4 text-lg font-bold text-white shadow-[0_0_36px_rgba(139,92,246,0.45)] transition hover:scale-[1.02] hover:shadow-[0_0_45px_rgba(217,70,239,0.45)]"
                            @click="openUploader"
                        >
                            Start creating
                            <span class="text-2xl leading-none">↗</span>
                        </button>
                    </div>

                    <div class="mt-10 flex flex-wrap items-center justify-center gap-x-4 gap-y-2 text-sm text-white/55">
                        <span class="text-violet-200">✦</span>
                        <span>Fast transcription</span>
                        <span class="h-1 w-1 rounded-full bg-white/20"></span>
                        <span>Word-level timing</span>
                        <span class="h-1 w-1 rounded-full bg-white/20"></span>
                        <span>SRT · VTT · MP4 exports</span>
                    </div>
                </div>

                <div
                    ref="uploaderCard"
                    class="mx-auto mt-12 max-w-4xl rounded-[32px] border border-white/10 bg-[#111321]/85 p-5 shadow-[0_24px_80px_rgba(0,0,0,0.55)] backdrop-blur-2xl md:p-8"
                >
                    <div class="mb-8 text-center">
                        <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-2xl border border-fuchsia-300/20 bg-gradient-to-br from-violet-500/20 to-fuchsia-500/20 text-2xl text-fuchsia-200 shadow-[0_0_28px_rgba(217,70,239,0.2)]">
                            ◈
                        </div>

                        <h3 class="text-2xl font-bold md:text-3xl">
                            Start a new subtitle project
                        </h3>

                        <p class="mx-auto mt-3 max-w-xl text-sm leading-relaxed text-white/45">
                            Drop in your media, choose a language, and let the lab build a first cut you can make your own.
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

                        <div class="md:col-span-2">
                            <label
                                class="mb-2 block text-xs font-medium uppercase tracking-wider text-white/40"
                            >
                                Subtitle language
                            </label>

                            <select
                                v-model="
                                    selectedLanguage
                                "
                                class="w-full rounded-2xl border border-white/10 bg-[#0b0d18]/80 px-4 py-3 text-sm text-white outline-none transition focus:border-violet-400/50 focus:ring-2 focus:ring-violet-400/10"
                            >
                                <option
                                    v-for="language in languages"
                                    :key="language.code"
                                    :value="language.code"
                                    class="bg-[#101522]"
                                >
                                    {{ language.name }} — {{ language.native }}
                                </option>
                            </select>

                            <p class="mt-2 text-xs text-white/35">
                                {{ selectedLanguage === 'auto'
                                    ? 'Whisper will detect the spoken language automatically.'
                                    : selectedLanguage === 'bn'
                                        ? 'Speech is detected automatically, then captions are translated into Bangla.'
                                    : `Captions will be generated in ${selectedLanguageName}.` }}
                            </p>
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
                                class="w-full rounded-2xl border border-white/10 bg-[#0b0d18]/80 px-4 py-3 text-sm text-white outline-none transition focus:border-violet-400/50 focus:ring-2 focus:ring-violet-400/10"
                            >
                                <option
                                    value="base"
                                    class="bg-[#101522]"
                                >
                                    Base — Fast
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
                                    Small — Recommended (Higher Accuracy)
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
                :saving="saving"
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

<style scoped>
    .hero-grid {
        background-image:
            linear-gradient(rgba(255, 255, 255, 0.04) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255, 255, 255, 0.04) 1px, transparent 1px);
        background-size: 42px 42px;
        mask-image: radial-gradient(circle at center, black 30%, transparent 100%);
    }

    .brand-word {
        font-family: "Arial Black", "Segoe UI", sans-serif;
        text-shadow: 0 0 20px rgba(167, 139, 250, 0.2);
    }

    .brand-mark {
        background: linear-gradient(135deg, rgba(139, 92, 246, 0.25), rgba(244, 114, 182, 0.08));
    }

    .hero-title {
        text-shadow: 0 0 35px rgba(167, 139, 250, 0.18);
    }

    .hero-gradient {
        background: linear-gradient(100deg, #c4b5fd 0%, #f0abfc 48%, #fda4af 100%);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }

    .hero-cta {
        min-width: min(100%, 420px);
    }

    .google-mark {
        font-family: "Arial", sans-serif;
    }
</style>
