<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import api from '../../services/api';

const email = ref('');
const password = ref('');

const loading = ref(false);
const error = ref('');
const errors = ref({});

async function login() {
    loading.value = true;
    error.value = '';
    errors.value = {};

    try {
        const response = await api.post(
            '/api/auth/login',
            {
                email: email.value,
                password: password.value,
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
                'Please check your information.';
        } else {
            error.value =
                err.response?.data?.message ||
                'Login failed. Please try again.';
        }
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div
        class="flex min-h-screen items-center justify-center bg-gray-50 px-4 dark:bg-gray-950"
    >
        <div
            class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-8 shadow-xl dark:border-gray-800 dark:bg-gray-900"
        >
            <!-- Logo -->
            <div class="mb-8 text-center">
                <div
                    class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-600 text-xl font-bold text-white"
                >
                    AI
                </div>

                <h1
                    class="text-2xl font-bold text-gray-900 dark:text-white"
                >
                    Welcome back
                </h1>

                <p
                    class="mt-2 text-sm text-gray-500 dark:text-gray-400"
                >
                    Sign in to your subtitle workspace
                </p>
            </div>

            <!-- Error -->
            <div
                v-if="error"
                class="mb-5 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/30 dark:text-red-400"
            >
                {{ error }}
            </div>

            <!-- Form -->
            <form
                @submit.prevent="login"
                class="space-y-5"
            >
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
                        class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
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
                        autocomplete="current-password"
                        placeholder="••••••••"
                        class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    />

                    <p
                        v-if="errors.password"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ errors.password[0] }}
                    </p>
                </div>

                <!-- Submit -->
                <button
                    type="submit"
                    :disabled="loading"
                    class="w-full rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {{
                        loading
                            ? 'Signing in...'
                            : 'Sign In'
                    }}
                </button>
            </form>

            <!-- Register -->
            <p
                class="mt-6 text-center text-sm text-gray-500 dark:text-gray-400"
            >
                Don't have an account?

                <a
                    href="/register"
                    class="font-semibold text-indigo-600 hover:text-indigo-700"
                >
                    Create account
                </a>
            </p>
        </div>
    </div>
</template>