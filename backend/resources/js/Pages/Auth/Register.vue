<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import api from '../../services/api';

const name = ref('');
const email = ref('');
const password = ref('');
const passwordConfirmation = ref('');

const loading = ref(false);
const error = ref('');
const errors = ref({});

async function register() {
    loading.value = true;
    error.value = '';
    errors.value = {};

    try {
        const response = await api.post(
            '/api/auth/register',
            {
                name: name.value,
                email: email.value,
                password: password.value,
                password_confirmation:
                    passwordConfirmation.value,
            }
        );

        localStorage.setItem(
            'auth_token',
            response.data.token
        );

        localStorage.setItem(
            'auth_user',
            JSON.stringify(
                response.data.user
            )
        );

        router.visit('/');
    } catch (err) {
        if (
            err.response?.status === 422
        ) {
            errors.value =
                err.response.data.errors || {};

            error.value =
                'Please correct the highlighted fields.';
        } else {
            error.value =
                err.response?.data?.message ||
                'Registration failed.';
        }
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div
        class="flex min-h-screen items-center justify-center bg-gray-50 px-4 py-10 dark:bg-gray-950"
    >
        <div
            class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-8 shadow-xl dark:border-gray-800 dark:bg-gray-900"
        >
            <div class="mb-8 text-center">
                <div
                    class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-600 text-xl font-bold text-white"
                >
                    AI
                </div>

                <h1
                    class="text-2xl font-bold text-gray-900 dark:text-white"
                >
                    Create your account
                </h1>

                <p
                    class="mt-2 text-sm text-gray-500 dark:text-gray-400"
                >
                    Start generating AI subtitles
                </p>
            </div>

            <div
                v-if="error"
                class="mb-5 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/30 dark:text-red-400"
            >
                {{ error }}
            </div>

            <form
                @submit.prevent="register"
                class="space-y-5"
            >
                <!-- Name -->
                <div>
                    <label
                        class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Full Name
                    </label>

                    <input
                        v-model="name"
                        type="text"
                        autocomplete="name"
                        placeholder="Your name"
                        class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm outline-none focus:border-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    />

                    <p
                        v-if="errors.name"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ errors.name[0] }}
                    </p>
                </div>

                <!-- Email -->
                <div>
                    <label
                        class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Email
                    </label>

                    <input
                        v-model="email"
                        type="email"
                        autocomplete="email"
                        placeholder="you@example.com"
                        class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm outline-none focus:border-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    />

                    <p
                        v-if="errors.email"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ errors.email[0] }}
                    </p>
                </div>

                <!-- Password -->
                <div>
                    <label
                        class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Password
                    </label>

                    <input
                        v-model="password"
                        type="password"
                        autocomplete="new-password"
                        placeholder="Minimum 8 characters"
                        class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm outline-none focus:border-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    />

                    <p
                        v-if="errors.password"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ errors.password[0] }}
                    </p>
                </div>

                <!-- Confirm Password -->
                <div>
                    <label
                        class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Confirm Password
                    </label>

                    <input
                        v-model="passwordConfirmation"
                        type="password"
                        autocomplete="new-password"
                        placeholder="Repeat your password"
                        class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm outline-none focus:border-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    />
                </div>

                <button
                    type="submit"
                    :disabled="loading"
                    class="w-full rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-60"
                >
                    {{
                        loading
                            ? 'Creating account...'
                            : 'Create Account'
                    }}
                </button>
            </form>

            <p
                class="mt-6 text-center text-sm text-gray-500 dark:text-gray-400"
            >
                Already have an account?

                <a
                    href="/login"
                    class="font-semibold text-indigo-600 hover:text-indigo-700"
                >
                    Sign in
                </a>
            </p>
        </div>
    </div>
</template>