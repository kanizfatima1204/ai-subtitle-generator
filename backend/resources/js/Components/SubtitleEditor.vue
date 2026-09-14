<script setup>
import { computed, ref } from 'vue'
import axios from 'axios'

const props = defineProps({
    subtitles: {
        type: Array,
        default: () => []
    },

    jobId: {
        type: Number,
        required: true
    }
})

const emit = defineEmits(['update'])

const editingIndex = ref(null)
const saving = ref(false)
const adding = ref(false)
const deletingIndex = ref(null)

const message = ref('')
const error = ref('')
const searchQuery = ref('')

/*
|--------------------------------------------------------------------------
| Computed
|--------------------------------------------------------------------------
*/

const filteredSubtitles = computed(() => {
    const query = searchQuery.value
        .trim()
        .toLowerCase()

    if (!query) {
        return props.subtitles
    }

    return props.subtitles.filter((subtitle) =>
        String(subtitle.text || '')
            .toLowerCase()
            .includes(query)
    )
})

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

const clearMessages = () => {
    message.value = ''
    error.value = ''
}

const startEdit = (index) => {
    clearMessages()

    editingIndex.value = index
}

const stopEdit = () => {
    editingIndex.value = null
}

const formatTime = (seconds) => {
    const value = Number(seconds)

    if (!Number.isFinite(value)) {
        return '00:00:00.000'
    }

    const hours = Math.floor(value / 3600)

    const minutes = Math.floor(
        (value % 3600) / 60
    )

    const remainingSeconds = Math.floor(
        value % 60
    )

    const milliseconds = Math.round(
        (value - Math.floor(value)) * 1000
    )

    return (
        String(hours).padStart(2, '0') +
        ':' +
        String(minutes).padStart(2, '0') +
        ':' +
        String(remainingSeconds).padStart(2, '0') +
        '.' +
        String(milliseconds).padStart(3, '0')
    )
}

const validateSubtitles = () => {
    for (
        let index = 0;
        index < props.subtitles.length;
        index++
    ) {
        const subtitle = props.subtitles[index]

        const start = Number(subtitle.start)
        const end = Number(subtitle.end)

        if (!subtitle.text?.trim()) {
            error.value =
                `Subtitle #${index + 1} cannot be empty.`

            return false
        }

        if (!Number.isFinite(start) || start < 0) {
            error.value =
                `Invalid start time for subtitle #${index + 1}.`

            return false
        }

        if (!Number.isFinite(end) || end <= start) {
            error.value =
                `End time must be greater than start time for subtitle #${index + 1}.`

            return false
        }

        /*
         * Prevent overlapping subtitles.
         */
        if (index > 0) {
            const previous =
                props.subtitles[index - 1]

            const previousEnd =
                Number(previous.end)

            if (start < previousEnd) {
                error.value =
                    `Subtitle #${index + 1} overlaps with subtitle #${index}.`

                return false
            }
        }
    }

    return true
}

/*
|--------------------------------------------------------------------------
| Save
|--------------------------------------------------------------------------
*/

const saveSubtitles = async () => {
    clearMessages()

    if (!validateSubtitles()) {
        return
    }

    saving.value = true

    try {
        const payload = {
            subtitles: props.subtitles.map(
                (subtitle) => ({
                    id: subtitle.id ?? null,
                    start: Number(subtitle.start),
                    end: Number(subtitle.end),
                    text: String(
                        subtitle.text || ''
                    ).trim()
                })
            )
        }

        const response = await axios.put(
            `/api/subtitle-jobs/${props.jobId}/subtitles`,
            payload
        )

        emit(
            'update',
            response.data.subtitles || []
        )

        editingIndex.value = null

        message.value =
            response.data.message ||
            'Subtitles saved successfully.'
    } catch (err) {
        console.error(err)

        if (err.response?.status === 422) {
            const validationErrors =
                err.response.data.errors

            if (validationErrors) {
                const firstError =
                    Object.values(validationErrors)[0]

                error.value = Array.isArray(firstError)
                    ? firstError[0]
                    : firstError
            } else {
                error.value =
                    'Please check your subtitle data.'
            }
        } else {
            error.value =
                err.response?.data?.message ||
                'Failed to save subtitles.'
        }
    } finally {
        saving.value = false
    }
}

/*
|--------------------------------------------------------------------------
| Add subtitle
|--------------------------------------------------------------------------
*/

const addSubtitle = () => {
    clearMessages()

    const lastSubtitle =
        props.subtitles[
            props.subtitles.length - 1
        ]

    const start = lastSubtitle
        ? Number(lastSubtitle.end)
        : 0

    const end = start + 2

    props.subtitles.push({
        id: null,
        sequence: props.subtitles.length + 1,
        start,
        end,
        text: 'New subtitle'
    })

    editingIndex.value =
        props.subtitles.length - 1

    adding.value = true

    setTimeout(() => {
        adding.value = false
    }, 300)
}

/*
|--------------------------------------------------------------------------
| Delete subtitle
|--------------------------------------------------------------------------
*/

const deleteSubtitle = async (index) => {
    clearMessages()

    if (props.subtitles.length <= 1) {
        error.value =
            'At least one subtitle is required.'

        return
    }

    const confirmed = window.confirm(
        `Delete subtitle #${index + 1}?`
    )

    if (!confirmed) {
        return
    }

    deletingIndex.value = index

    props.subtitles.splice(index, 1)

    /*
     * Recalculate sequence numbers.
     */
    props.subtitles.forEach(
        (subtitle, position) => {
            subtitle.sequence =
                position + 1
        }
    )

    if (
        editingIndex.value === index
    ) {
        editingIndex.value = null
    }

    if (
        editingIndex.value !== null &&
        editingIndex.value > index
    ) {
        editingIndex.value--
    }

    deletingIndex.value = null

    message.value =
        'Subtitle removed. Click Save Changes to apply it permanently.'
}

/*
|--------------------------------------------------------------------------
| Duplicate subtitle
|--------------------------------------------------------------------------
*/

const duplicateSubtitle = (index) => {
    clearMessages()

    const source =
        props.subtitles[index]

    const start =
        Number(source.end)

    const end =
        start + (
            Number(source.end) -
            Number(source.start)
        )

    props.subtitles.splice(
        index + 1,
        0,
        {
            id: null,
            sequence: index + 2,
            start,
            end,
            text: source.text
        }
    )

    props.subtitles.forEach(
        (subtitle, position) => {
            subtitle.sequence =
                position + 1
        }
    )

    editingIndex.value = index + 1

    message.value =
        'Subtitle duplicated. Click Save Changes to apply it.'
}

/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

const clearSearch = () => {
    searchQuery.value = ''
}
</script>

<template>
    <div
        class="space-y-6"
    >

        <!-- ========================================================= -->
        <!-- Header -->
        <!-- ========================================================= -->

        <div
            class="rounded-2xl border
                   border-gray-200 bg-white p-6
                   shadow-sm
                   dark:border-gray-800
                   dark:bg-gray-900"
        >

            <div
                class="flex flex-col gap-5
                       lg:flex-row
                       lg:items-center
                       lg:justify-between"
            >

                <!-- Title -->
                <div>

                    <div
                        class="flex items-center gap-3"
                    >
                        <div
                            class="flex h-11 w-11
                                   items-center justify-center
                                   rounded-xl bg-indigo-100
                                   text-indigo-600
                                   dark:bg-indigo-950
                                   dark:text-indigo-400"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-6 w-6"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M4 6h16M4 12h16M4 18h10"
                                />
                            </svg>
                        </div>

                        <div>
                            <h2
                                class="text-xl font-bold"
                            >
                                Subtitle Editor
                            </h2>

                            <p
                                class="text-sm text-gray-500
                                       dark:text-gray-400"
                            >
                                Edit subtitle text and timing
                            </p>
                        </div>
                    </div>

                </div>

                <!-- Actions -->
                <div
                    class="flex flex-wrap gap-3"
                >

                    <button
                        type="button"
                        @click="addSubtitle"
                        :disabled="adding || saving"
                        class="inline-flex items-center
                               gap-2 rounded-lg
                               border border-gray-300
                               bg-white px-4 py-2.5
                               text-sm font-medium
                               text-gray-700
                               transition
                               hover:bg-gray-50
                               disabled:cursor-not-allowed
                               disabled:opacity-50
                               dark:border-gray-700
                               dark:bg-gray-800
                               dark:text-gray-200
                               dark:hover:bg-gray-700"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-4 w-4"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 4v16m8-8H4"
                            />
                        </svg>

                        Add Subtitle
                    </button>

                    <button
                        type="button"
                        @click="saveSubtitles"
                        :disabled="saving"
                        class="inline-flex items-center
                               gap-2 rounded-lg
                               bg-indigo-600 px-5 py-2.5
                               text-sm font-semibold
                               text-white
                               transition
                               hover:bg-indigo-700
                               disabled:cursor-not-allowed
                               disabled:opacity-50"
                    >

                        <svg
                            v-if="!saving"
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-4 w-4"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M5 13l4 4L19 7"
                            />
                        </svg>

                        <svg
                            v-else
                            class="h-4 w-4 animate-spin"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            />

                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
                            />
                        </svg>

                        {{
                            saving
                                ? 'Saving...'
                                : 'Save Changes'
                        }}
                    </button>

                </div>

            </div>

            <!-- ===================================================== -->
            <!-- Search -->
            <!-- ===================================================== -->

            <div
                class="mt-6"
            >
                <div
                    class="relative"
                >

                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="pointer-events-none
                               absolute left-3 top-1/2
                               h-5 w-5 -translate-y-1/2
                               text-gray-400"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M21 21l-4.35-4.35m2.35-5.65a8 8 0 11-16 0 8 8 0 0116 0z"
                        />
                    </svg>

                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search subtitles..."
                        class="w-full rounded-xl
                               border border-gray-300
                               bg-gray-50 py-3 pl-10 pr-10
                               text-sm outline-none
                               transition
                               focus:border-indigo-500
                               focus:ring-2
                               focus:ring-indigo-500/20
                               dark:border-gray-700
                               dark:bg-gray-800
                               dark:text-white
                               dark:placeholder-gray-500"
                    />

                    <button
                        v-if="searchQuery"
                        type="button"
                        @click="clearSearch"
                        class="absolute right-3
                               top-1/2
                               -translate-y-1/2
                               text-gray-400
                               hover:text-gray-700
                               dark:hover:text-gray-200"
                    >
                        ×
                    </button>

                </div>
            </div>

        </div>

        <!-- ========================================================= -->
        <!-- Messages -->
        <!-- ========================================================= -->

        <div
            v-if="message"
            class="rounded-xl border
                   border-green-200
                   bg-green-50 px-4 py-3
                   text-sm text-green-700
                   dark:border-green-900
                   dark:bg-green-950/30
                   dark:text-green-300"
        >
            <div
                class="flex items-center gap-2"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-5 w-5"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M5 13l4 4L19 7"
                    />
                </svg>

                {{ message }}
            </div>
        </div>

        <div
            v-if="error"
            class="rounded-xl border
                   border-red-200
                   bg-red-50 px-4 py-3
                   text-sm text-red-700
                   dark:border-red-900
                   dark:bg-red-950/30
                   dark:text-red-300"
        >
            <div
                class="flex items-start gap-2"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="mt-0.5 h-5 w-5 shrink-0"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M12 9v4m0 4h.01M10.29 3.86l-8.82 15a2 2 0 001.71 3h17.64a2 2 0 001.71-3l-8.82-15a2 2 0 00-3.42 0z"
                    />
                </svg>

                {{ error }}
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- Stats -->
        <!-- ========================================================= -->

        <div
            class="grid gap-4 sm:grid-cols-3"
        >

            <div
                class="rounded-xl border
                       border-gray-200 bg-white p-5
                       dark:border-gray-800
                       dark:bg-gray-900"
            >
                <p
                    class="text-xs font-medium uppercase
                           tracking-wide text-gray-500"
                >
                    Total Subtitles
                </p>

                <p
                    class="mt-2 text-2xl font-bold"
                >
                    {{ subtitles.length }}
                </p>
            </div>

            <div
                class="rounded-xl border
                       border-gray-200 bg-white p-5
                       dark:border-gray-800
                       dark:bg-gray-900"
            >
                <p
                    class="text-xs font-medium uppercase
                           tracking-wide text-gray-500"
                >
                    Showing
                </p>

                <p
                    class="mt-2 text-2xl font-bold"
                >
                    {{ filteredSubtitles.length }}
                </p>
            </div>

            <div
                class="rounded-xl border
                       border-gray-200 bg-white p-5
                       dark:border-gray-800
                       dark:bg-gray-900"
            >
                <p
                    class="text-xs font-medium uppercase
                           tracking-wide text-gray-500"
                >
                    Status
                </p>

                <p
                    class="mt-2 text-2xl font-bold
                           text-green-600"
                >
                    Ready
                </p>
            </div>

        </div>

        <!-- ========================================================= -->
        <!-- Subtitle List -->
        <!-- ========================================================= -->

        <div
            class="space-y-4"
        >

            <div
                v-if="!filteredSubtitles.length"
                class="rounded-2xl border
                       border-gray-200 bg-white p-10
                       text-center
                       dark:border-gray-800
                       dark:bg-gray-900"
            >
                <div
                    class="mx-auto flex h-14 w-14
                           items-center justify-center
                           rounded-full bg-gray-100
                           text-gray-500
                           dark:bg-gray-800"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-7 w-7"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M21 21l-4.35-4.35m2.35-5.65a8 8 0 11-16 0 8 8 0 0116 0z"
                        />
                    </svg>
                </div>

                <h3
                    class="mt-4 text-lg font-semibold"
                >
                    No subtitles found
                </h3>

                <p
                    class="mt-2 text-sm text-gray-500
                           dark:text-gray-400"
                >
                    Try a different search term.
                </p>
            </div>

            <!-- Subtitle -->
            <div
                v-for="(subtitle, index) in filteredSubtitles"
                :key="subtitle.id ?? `${subtitle.sequence}-${index}`"
                class="rounded-2xl border
                       border-gray-200 bg-white
                       shadow-sm transition
                       dark:border-gray-800
                       dark:bg-gray-900"
            >

                <!-- Subtitle Header -->
                <div
                    class="flex flex-col gap-3
                           border-b border-gray-100
                           px-5 py-4
                           sm:flex-row
                           sm:items-center
                           sm:justify-between
                           dark:border-gray-800"
                >

                    <div
                        class="flex items-center gap-3"
                    >

                        <span
                            class="flex h-8 w-8
                                   items-center justify-center
                                   rounded-lg
                                   bg-indigo-100
                                   text-xs font-bold
                                   text-indigo-700
                                   dark:bg-indigo-950
                                   dark:text-indigo-300"
                        >
                            {{ subtitle.sequence || index + 1 }}
                        </span>

                        <div>
                            <p
                                class="text-xs font-medium
                                       uppercase
                                       tracking-wide
                                       text-gray-400"
                            >
                                Subtitle
                            </p>

                            <p
                                class="text-sm font-semibold"
                            >
                                #{{ subtitle.sequence || index + 1 }}
                            </p>
                        </div>

                    </div>

                    <!-- Actions -->
                    <div
                        class="flex flex-wrap items-center gap-2"
                    >

                        <button
                            type="button"
                            @click="duplicateSubtitle(index)"
                            class="rounded-lg border
                                   border-gray-200
                                   px-3 py-2
                                   text-xs font-medium
                                   text-gray-600
                                   transition
                                   hover:bg-gray-50
                                   dark:border-gray-700
                                   dark:text-gray-300
                                   dark:hover:bg-gray-800"
                        >
                            Duplicate
                        </button>

                        <button
                            type="button"
                            @click="deleteSubtitle(index)"
                            :disabled="
                                deletingIndex === index
                            "
                            class="rounded-lg border
                                   border-red-200
                                   px-3 py-2
                                   text-xs font-medium
                                   text-red-600
                                   transition
                                   hover:bg-red-50
                                   disabled:opacity-50
                                   dark:border-red-900
                                   dark:text-red-400
                                   dark:hover:bg-red-950"
                        >
                            {{
                                deletingIndex === index
                                    ? 'Deleting...'
                                    : 'Delete'
                            }}
                        </button>

                        <button
                            v-if="editingIndex !== index"
                            type="button"
                            @click="startEdit(index)"
                            class="rounded-lg
                                   bg-indigo-600 px-4 py-2
                                   text-xs font-semibold
                                   text-white
                                   transition
                                   hover:bg-indigo-700"
                        >
                            Edit
                        </button>

                        <button
                            v-else
                            type="button"
                            @click="stopEdit"
                            class="rounded-lg
                                   bg-gray-800 px-4 py-2
                                   text-xs font-semibold
                                   text-white
                                   transition
                                   hover:bg-gray-900
                                   dark:bg-gray-700"
                        >
                            Done
                        </button>

                    </div>

                </div>

                <!-- ================================================= -->
                <!-- Editing Mode -->
                <!-- ================================================= -->

                <div
                    v-if="editingIndex === index"
                    class="space-y-5 p-5"
                >

                    <!-- Timing -->
                    <div
                        class="grid gap-4 md:grid-cols-2"
                    >

                        <div>
                            <label
                                class="mb-2 block text-xs
                                       font-semibold
                                       text-gray-600
                                       dark:text-gray-300"
                            >
                                Start Time
                            </label>

                            <div
                                class="relative"
                            >
                                <input
                                    v-model.number="
                                        subtitle.start
                                    "
                                    type="number"
                                    min="0"
                                    step="0.001"
                                    class="w-full rounded-xl
                                           border
                                           border-gray-300
                                           bg-gray-50
                                           px-4 py-3 pr-12
                                           text-sm
                                           outline-none
                                           focus:border-indigo-500
                                           focus:ring-2
                                           focus:ring-indigo-500/20
                                           dark:border-gray-700
                                           dark:bg-gray-800
                                           dark:text-white"
                                />

                                <span
                                    class="absolute right-4
                                           top-1/2
                                           -translate-y-1/2
                                           text-xs
                                           text-gray-400"
                                >
                                    sec
                                </span>
                            </div>

                            <p
                                class="mt-1 text-xs
                                       text-gray-400"
                            >
                                {{
                                    formatTime(
                                        subtitle.start
                                    )
                                }}
                            </p>
                        </div>

                        <div>
                            <label
                                class="mb-2 block text-xs
                                       font-semibold
                                       text-gray-600
                                       dark:text-gray-300"
                            >
                                End Time
                            </label>

                            <div
                                class="relative"
                            >
                                <input
                                    v-model.number="
                                        subtitle.end
                                    "
                                    type="number"
                                    min="0"
                                    step="0.001"
                                    class="w-full rounded-xl
                                           border
                                           border-gray-300
                                           bg-gray-50
                                           px-4 py-3 pr-12
                                           text-sm
                                           outline-none
                                           focus:border-indigo-500
                                           focus:ring-2
                                           focus:ring-indigo-500/20
                                           dark:border-gray-700
                                           dark:bg-gray-800
                                           dark:text-white"
                                />

                                <span
                                    class="absolute right-4
                                           top-1/2
                                           -translate-y-1/2
                                           text-xs
                                           text-gray-400"
                                >
                                    sec
                                </span>
                            </div>

                            <p
                                class="mt-1 text-xs
                                       text-gray-400"
                            >
                                {{
                                    formatTime(
                                        subtitle.end
                                    )
                                }}
                            </p>
                        </div>

                    </div>

                    <!-- Text -->
                    <div>
                        <label
                            class="mb-2 block text-xs
                                   font-semibold
                                   text-gray-600
                                   dark:text-gray-300"
                        >
                            Subtitle Text
                        </label>

                        <textarea
                            v-model="subtitle.text"
                            rows="4"
                            maxlength="1000"
                            class="w-full resize-y
                                   rounded-xl border
                                   border-gray-300
                                   bg-gray-50
                                   px-4 py-3
                                   text-base
                                   leading-relaxed
                                   outline-none
                                   transition
                                   focus:border-indigo-500
                                   focus:ring-2
                                   focus:ring-indigo-500/20
                                   dark:border-gray-700
                                   dark:bg-gray-800
                                   dark:text-white"
                            placeholder="Enter subtitle text..."
                        ></textarea>

                        <div
                            class="mt-1 flex justify-between
                                   text-xs text-gray-400"
                        >
                            <span>
                                Maximum 1000 characters
                            </span>

                            <span>
                                {{
                                    subtitle.text?.length || 0
                                }}/1000
                            </span>
                        </div>
                    </div>

                </div>

                <!-- ================================================= -->
                <!-- Preview Mode -->
                <!-- ================================================= -->

                <div
                    v-else
                    class="p-5"
                >

                    <div
                        class="mb-4 flex flex-wrap
                               items-center gap-3"
                    >

                        <span
                            class="rounded-lg
                                   bg-gray-100 px-3 py-1.5
                                   font-mono text-xs
                                   font-medium text-gray-600
                                   dark:bg-gray-800
                                   dark:text-gray-300"
                        >
                            {{ formatTime(subtitle.start) }}
                        </span>

                        <span
                            class="text-gray-300"
                        >
                            →
                        </span>

                        <span
                            class="rounded-lg
                                   bg-gray-100 px-3 py-1.5
                                   font-mono text-xs
                                   font-medium text-gray-600
                                   dark:bg-gray-800
                                   dark:text-gray-300"
                        >
                            {{ formatTime(subtitle.end) }}
                        </span>

                        <span
                            class="text-xs text-gray-400"
                        >
                            {{
                                (
                                    Number(subtitle.end) -
                                    Number(subtitle.start)
                                ).toFixed(3)
                            }}s duration
                        </span>

                    </div>

                    <div
                        class="rounded-xl bg-gray-50 p-5
                               dark:bg-gray-800/60"
                    >
                        <p
                            class="whitespace-pre-line
                                   text-base font-medium
                                   leading-relaxed
                                   text-gray-800
                                   dark:text-gray-100"
                        >
                            {{ subtitle.text }}
                        </p>
                    </div>

                </div>

            </div>

        </div>

        <!-- ========================================================= -->
        <!-- Bottom Save -->
        <!-- ========================================================= -->

        <div
            v-if="subtitles.length"
            class="sticky bottom-4 z-10"
        >
            <div
                class="flex flex-col gap-3
                       rounded-2xl border
                       border-gray-200 bg-white/95
                       p-4 shadow-lg
                       backdrop-blur
                       sm:flex-row
                       sm:items-center
                       sm:justify-between
                       dark:border-gray-800
                       dark:bg-gray-900/95"
            >

                <div>
                    <p
                        class="text-sm font-semibold"
                    >
                        Ready to export?
                    </p>

                    <p
                        class="text-xs text-gray-500
                               dark:text-gray-400"
                    >
                        Save your changes before
                        downloading the SRT file.
                    </p>
                </div>

                <button
                    type="button"
                    @click="saveSubtitles"
                    :disabled="saving"
                    class="rounded-lg bg-indigo-600
                           px-6 py-2.5
                           text-sm font-semibold
                           text-white
                           transition
                           hover:bg-indigo-700
                           disabled:cursor-not-allowed
                           disabled:opacity-50"
                >
                    {{
                        saving
                            ? 'Saving...'
                            : 'Save & Regenerate SRT'
                    }}
                </button>

            </div>
        </div>

    </div>
</template>
