import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from '@/app/App.vue';
import { router } from '@/app/router';
import { useAppConfigStore } from '@/modules/app-config/store';
import { useAuthStore } from '@/modules/auth/store';

const app = createApp(App);
app.use(createPinia());

// Кто вошёл и что интерфейсу нельзя зашивать у себя — известно до отрисовки первого экрана,
// поэтому при перезагрузке страницы охранники маршрутов принимают верное решение.
await Promise.all([useAuthStore().restore(), useAppConfigStore().load()]);

app.use(router);
app.mount('#app');
