import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/modules/auth/store';

export const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/login',
            name: 'login',
            component: () => import('@/pages/LoginPage.vue'),
            meta: { guestOnly: true },
        },
        {
            path: '/',
            name: 'organizations',
            component: () => import('@/pages/OrganizationsPage.vue'),
            meta: { requiresAuth: true },
        },
        {
            path: '/organizations/:id',
            name: 'organization',
            component: () => import('@/pages/OrganizationPage.vue'),
            props: true,
            meta: { requiresAuth: true },
        },
        { path: '/:path(.*)*', redirect: { name: 'organizations' } },
    ],
});

router.beforeEach((to) => {
    const auth = useAuthStore();

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    if (to.meta.guestOnly && auth.isAuthenticated) {
        return { name: 'organizations' };
    }

    return true;
});
