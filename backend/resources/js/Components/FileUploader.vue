<script setup>
import { ref } from 'vue';

const emit = defineEmits(['upload', 'uploaded']);

const file = ref(null);
const isDragging = ref(false);
const error = ref('');

const supportedExtensions = ['mp4', 'mov', 'mkv', 'avi', 'webm', 'mp3', 'wav', 'm4a', 'aac', 'flac', 'ogg'];

function validateAndEmit(selected) {
    if (!selected) return;
    
    // Check file extension / MIME
    const ext = selected.name.split('.').pop()?.toLowerCase();
    const isVideoOrAudio = selected.type.startsWith('video/') || selected.type.startsWith('audio/') || supportedExtensions.includes(ext);

    if (!isVideoOrAudio) {
        error.value = 'Please select a valid video or audio file (MP4, MOV, MKV, MP3, WAV, etc.)';
        return;
    }

    // Check size (e.g. 500MB max)
    if (selected.size > 500 * 1024 * 1024) {
        error.value = 'File size exceeds 500MB limit.';
        return;
    }

    error.value = '';
    file.value = selected;
    emit('upload', selected);
    emit('uploaded', selected);
}

function handleFile(event) {
    const selected = event.target.files?.[0];
    validateAndEmit(selected);
}

function handleDrop(event) {
    isDragging.value = false;
    const selected = event.dataTransfer?.files?.[0];
    validateAndEmit(selected);
}

function handleDragOver() {
    isDragging.value = true;
}

function handleDragLeave() {
    isDragging.value = false;
}
</script>

<template>
    <div class="w-full">
        <label
            class="relative flex cursor-pointer flex-col items-center justify-center rounded-3xl border-2 border-dashed p-10 transition-all duration-300"
            :class="[
                isDragging
                    ? 'border-indigo-400 bg-indigo-500/10 shadow-[0_0_30px_rgba(99,102,241,0.2)]'
                    : 'border-white/15 bg-white/[0.03] hover:border-white/30 hover:bg-white/[0.05]'
            ]"
            @dragover.prevent="handleDragOver"
            @dragleave.prevent="handleDragLeave"
            @drop.prevent="handleDrop"
        >
            <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-indigo-500/15 text-indigo-400 ring-1 ring-indigo-500/30">
                <svg class="h-8 w-8 transition-transform group-hover:scale-110" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>
            </div>

            <span class="text-base font-semibold text-white">
                Drag & drop your video or audio here
            </span>

            <span class="mt-1 text-xs text-white/50">
                Supports MP4, MOV, MKV, MP3, WAV, M4A, FLAC (up to 500MB)
            </span>

            <span class="mt-5 inline-flex items-center gap-2 rounded-xl bg-white/10 px-4 py-2 text-xs font-medium text-white/80 backdrop-blur transition hover:bg-white/20 hover:text-white">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Browse Files
            </span>

            <input
                type="file"
                class="hidden"
                accept="video/*,audio/*,.mp4,.mov,.mkv,.avi,.webm,.mp3,.wav,.m4a,.aac,.flac,.ogg"
                @change="handleFile"
            />
        </label>

        <!-- Selected File Banner -->
        <div
            v-if="file"
            class="mt-4 flex items-center justify-between rounded-2xl border border-white/10 bg-white/[0.05] p-4 text-sm backdrop-blur"
        >
            <div class="flex items-center gap-3 truncate">
                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/30">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="truncate">
                    <p class="truncate font-medium text-white">
                        {{ file.name }}
                    </p>
                    <p class="text-xs text-white/40">
                        {{ (file.size / (1024 * 1024)).toFixed(2) }} MB
                    </p>
                </div>
            </div>
            <span class="rounded-lg bg-emerald-500/20 px-2.5 py-1 text-xs font-medium text-emerald-300">
                Ready
            </span>
        </div>

        <!-- Validation Error Message -->
        <div
            v-if="error"
            class="mt-3 rounded-xl border border-rose-500/30 bg-rose-500/10 p-3 text-xs text-rose-300"
        >
            {{ error }}
        </div>
    </div>
</template>
