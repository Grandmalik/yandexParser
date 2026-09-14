import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { currentUser, login, logout } from './api';
import type { LoginData, UserData } from '@/types/generated/api';

export const useAuthStore = defineStore('auth', () => {
    const user = ref<UserData | null>(null);
    const isAuthenticated = computed(() => user.value !== null);

    /** Спрашивает у API, кто мы; отсутствие сессии — это нормальный ответ, а не ошибка. */
    async function restore(): Promise<void> {
        try {
            user.value = await currentUser();
        } catch {
            user.value = null;
        }
    }

    async function signIn(credentials: LoginData): Promise<void> {
        user.value = await login(credentials);
    }

    async function signOut(): Promise<void> {
        try {
            await logout();
        } finally {
            user.value = null;
        }
    }

    return { user, isAuthenticated, restore, signIn, signOut };
});
