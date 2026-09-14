<script setup lang="ts">
import { reactive } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/modules/auth/store';
import { useAsync } from '@/shared/composables/useAsync';
import { ru } from '@/shared/i18n/ru';
import type { LoginData } from '@/types/generated/api';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const credentials = reactive<LoginData>({ email: '', password: '', remember: false });
const { error, isLoading, execute } = useAsync(async () => {
    await auth.signIn(credentials);

    const redirect = route.query.redirect;
    await router.push(typeof redirect === 'string' ? redirect : { name: 'organizations' });
});
</script>

<template>
    <form
        class="mx-auto max-w-sm rounded-lg border border-gray-200 bg-white p-6 shadow-sm"
        @submit.prevent="execute()"
    >
        <h1 class="mb-6 text-xl font-semibold">{{ ru.login.title }}</h1>

        <!-- Сообщение не про конкретное поле: неверная пара, слишком частые попытки, проблема на сервере. -->
        <p
            v-if="error && !error.fields"
            class="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-700"
            role="alert"
        >
            {{ error.message }}
        </p>

        <label class="mb-4 block">
            <span class="mb-1 block text-sm text-gray-700">{{ ru.login.email }}</span>
            <input
                v-model="credentials.email"
                type="email"
                autocomplete="username"
                required
                class="w-full rounded-md border border-gray-300 px-3 py-2"
            />
            <span v-if="error?.fieldError('email')" class="mt-1 block text-sm text-red-700">
                {{ error.fieldError('email') }}
            </span>
        </label>

        <label class="mb-4 block">
            <span class="mb-1 block text-sm text-gray-700">{{ ru.login.password }}</span>
            <input
                v-model="credentials.password"
                type="password"
                autocomplete="current-password"
                required
                class="w-full rounded-md border border-gray-300 px-3 py-2"
            />
            <span v-if="error?.fieldError('password')" class="mt-1 block text-sm text-red-700">
                {{ error.fieldError('password') }}
            </span>
        </label>

        <label class="mb-6 flex items-center gap-2 text-sm text-gray-700">
            <input v-model="credentials.remember" type="checkbox" />
            {{ ru.login.remember }}
        </label>

        <button
            type="submit"
            :disabled="isLoading"
            class="w-full rounded-md bg-gray-900 px-4 py-2 text-white disabled:opacity-60"
        >
            {{ isLoading ? ru.login.submitting : ru.login.submit }}
        </button>
    </form>
</template>
