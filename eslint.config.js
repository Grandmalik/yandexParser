import pluginVue from 'eslint-plugin-vue';
import { defineConfigWithVueTs, vueTsConfigs } from '@vue/eslint-config-typescript';
import skipFormatting from '@vue/eslint-config-prettier/skip-formatting';

export default defineConfigWithVueTs(
    {
        name: 'app/files-to-lint',
        files: ['resources/js/**/*.{ts,vue}'],
    },
    {
        name: 'app/files-to-ignore',
        ignores: [
            'public/**',
            'vendor/**',
            'storage/**',
            'bootstrap/**',
            'resources/js/types/generated/**',
            // Separate package with its own tooling.
            'services/**',
        ],
    },
    pluginVue.configs['flat/recommended'],
    vueTsConfigs.recommended,
    skipFormatting,
);
