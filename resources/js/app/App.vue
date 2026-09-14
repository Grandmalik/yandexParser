<script setup lang="ts">
import { useRouter } from 'vue-router';
import { useAppConfigStore } from '@/modules/app-config/store';
import { useAuthStore } from '@/modules/auth/store';
import { disconnect } from '@/shared/realtime/echo';
import { ru } from '@/shared/i18n/ru';

const auth = useAuthStore();
const appConfig = useAppConfigStore();
const router = useRouter();

async function signOut(): Promise<void> {
    await auth.signOut();
    // Websocket-соединение было авторизовано сессией, которая только что закончилась.
    disconnect();
    await router.push({ name: 'login' });
}
</script>

<template>
    <div class="min-h-screen">
        <header v-if="auth.isAuthenticated" class="border-b border-gray-200 bg-white">
            <div class="mx-auto flex max-w-4xl items-center justify-between px-4 py-3">
                <span class="font-semibold">{{ appConfig.applicationName }}</span>

                <div class="flex items-center gap-3 text-sm">
                    <span class="text-gray-600">{{ auth.user?.email }}</span>
                    <button
                        type="button"
                        class="text-gray-600 underline hover:text-gray-900"
                        @click="signOut"
                    >
                        {{ ru.header.signOut }}
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-4xl px-4 py-8">
            <RouterView />
        </main>
    </div>
</template>
