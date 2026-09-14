<script setup>
import { onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import api from '../services/api';

const jobs = ref([]);
const loading = ref(true);
const error = ref('');

async function loadJobs() {
    loading.value = true;
    error.value = '';

    try {
        const response = await api.get(
            '/api/subtitle-jobs'
        );

        jobs.value =
            response.data.data || [];
    } catch (err) {
        if (
            err.response?.status === 401
        ) {
            window.location.href = '/login';
            return;
        }

        error.value =
            err.response?.data?.message ||
            'Unable to load subtitle history.';
    } finally {
        loading.value = false;
    }
}

function openJob(job) {
    router.visit(
        `/?job=${job.id}`
    );
}

function formatDate(date) {
    if (!date) return '-';

    return new Date(date).toLocaleString();
}

function formatFileSize(bytes) {
    if (!bytes) return '0 KB';

    const units = [
        'Bytes',
        'KB',
        'MB',
        'GB',
    ];

    let size = Number(bytes);
    let index = 0;

    while (
        size >= 1024 &&
        index < units.length - 1
    ) {
        size /= 1024;
        index++;
    }

    return `${size.toFixed(1)} ${units[index]}`;
}

function statusClasses(status) {
    switch (status) {
        case 'completed':
            return 'bg-green-100 text-green-700 dark:bg-green-950/40 dark:text-green-400';

        case 'processing':
            return 'bg-blue-100 text-blue-700 dark:bg-blue-950/40 dark:text-blue-400';

        case 'pending':
            return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-950/40 dark:text-yellow-400';

        case 'failed':
            return 'bg-red-100 text-red-700 dark:bg-red-950/40 dark:text-red-400';

        default:
            return 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400';
    }
}

onMounted(() => {
    const token =
        localStorage.getItem('auth_token');

    if (!token) {
        window.location.href = '/login';
        return;
    }

    loadJobs();
});
</script>

<template>
    <div
        class="min-h-screen bg-gray-50 dark:bg-gray-950"
    >
        <!-- Header -->
        <header
            class="border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
        >
            <div
                class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4"
            >
                <div>
                    <h1
                        class="text-xl font-bold text-gray-900 dark:text-white"
                    >
                        Subtitle History
                    </h1>

                    <p
                        class="text-sm text-gray-500 dark:text-gray-400"
                    >
                        Your previous subtitle projects
                    </p>
                </div>

                <button
                    type="button"
                    @click="router.visit('/')"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
                >
                    New Project
                </button>
            </div>
        </header>

        <main
            class="mx-auto max-w-7xl px-6 py-8"
        >
            <!-- Error -->
            <div
                v-if="error"
                class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-400"
            >
                {{ error }}
            </div>

            <!-- Loading -->
            <div
                v-if="loading"
                class="rounded-2xl border border-gray-200 bg-white p-12 text-center dark:border-gray-800 dark:bg-gray-900"
            >
                <div
                    class="mx-auto h-10 w-10 animate-spin rounded-full border-4 border-gray-200 border-t-indigo-600"
                ></div>

                <p
                    class="mt-4 text-sm text-gray-500 dark:text-gray-400"
                >
                    Loading your projects...
                </p>
            </div>

            <!-- Empty -->
            <div
                v-else-if="jobs.length === 0"
                class="rounded-2xl border border-gray-200 bg-white p-12 text-center dark:border-gray-800 dark:bg-gray-900"
            >
                <div
                    class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-400"
                >
                    +
                </div>

                <h2
                    class="mt-4 text-lg font-semibold text-gray-900 dark:text-white"
                >
                    No subtitle projects yet
                </h2>

                <p
                    class="mt-2 text-sm text-gray-500 dark:text-gray-400"
                >
                    Upload your first video or audio
                    file to get started.
                </p>

                <button
                    type="button"
                    @click="router.visit('/')"
                    class="mt-6 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700"
                >
                    Create Project
                </button>
            </div>

            <!-- Desktop Table -->
            <div
                v-else
                class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <div
                    class="overflow-x-auto"
                >
                    <table
                        class="w-full min-w-[800px]"
                    >
                        <thead>
                            <tr
                                class="border-b border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/50"
                            >
                                <th
                                    class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-gray-500"
                                >
                                    File
                                </th>

                                <th
                                    class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-gray-500"
                                >
                                    Language
                                </th>

                                <th
                                    class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-gray-500"
                                >
                                    Status
                                </th>

                                <th
                                    class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-gray-500"
                                >
                                    Size
                                </th>

                                <th
                                    class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-gray-500"
                                >
                                    Created
                                </th>

                                <th
                                    class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wider text-gray-500"
                                >
                                    Action
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="job in jobs"
                                :key="job.id"
                                class="border-b border-gray-100 last:border-0 dark:border-gray-800"
                            >
                                <td
                                    class="px-6 py-4"
                                >
                                    <div
                                        class="font-medium text-gray-900 dark:text-white"
                                    >
                                        {{
                                            job.original_filename
                                        }}
                                    </div>

                                    <div
                                        class="mt-1 text-xs text-gray-500"
                                    >
                                        Job #{{ job.id }}
                                    </div>
                                </td>

                                <td
                                    class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300"
                                >
                                    {{
                                        job.language ||
                                        'Auto'
                                    }}
                                </td>

                                <td
                                    class="px-6 py-4"
                                >
                                    <span
                                        class="rounded-full px-3 py-1 text-xs font-semibold capitalize"
                                        :class="
                                            statusClasses(
                                                job.status
                                            )
                                        "
                                    >
                                        {{ job.status }}
                                    </span>
                                </td>

                                <td
                                    class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300"
                                >
                                    {{
                                        formatFileSize(
                                            job.file_size
                                        )
                                    }}
                                </td>

                                <td
                                    class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300"
                                >
                                    {{
                                        formatDate(
                                            job.created_at
                                        )
                                    }}
                                </td>

                                <td
                                    class="px-6 py-4 text-right"
                                >
                                    <button
                                        type="button"
                                        @click="
                                            openJob(
                                                job
                                            )
                                        "
                                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                                    >
                                        Open
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</template>
