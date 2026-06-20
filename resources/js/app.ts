import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';


import { createApp, h } from 'vue'; 

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),

    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob('./pages/**/*.vue')),

    setup({ el, App, props, plugin }) {
        //  On initialise l'application avec createSSRApp
        const vueApp = createApp({ render: () => h(App, props) })
            .use(plugin);
            
        //  On empêche Vue d'essayer de se "monter" si on est sur le serveur
        if (typeof window !== 'undefined' && el) {
            vueApp.mount(el);
        }
        
        return vueApp;
    },

    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#4B5563',
    },
});

//  On isole les fonctions propres au navigateur dans une condition
if (typeof window !== 'undefined') {
    // This will set light / dark mode on page load...
    initializeTheme();

    // This will listen for flash toast data from the server...
    initializeFlashToast();
}