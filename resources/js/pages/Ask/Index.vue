<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, nextTick, watch, computed, onMounted } from 'vue'; 
import MarkdownIt from 'markdown-it';
import hljs from 'highlight.js';
import 'highlight.js/styles/github-dark.css';

import { useStream } from '@laravel/stream-vue';

const props = defineProps({
    conversations: { type: Array, default: () => [] },
    activeConversation: { type: Object, default: null },
    messages: { type: Array, default: () => [] },
    models: { type: Array, default: () => [] },
    selectedModel: { type: String, default: '' },
    flash: { type: Object, default: () => ({}) },
    custom_instructions: { type: String, default: '' },
    theme: { type: String, default: 'light' }
});

// Changement au bouton theme
const currentTheme = ref(props.theme);

// Gestion de la fenêtre d'instructions
const showInstructionsModal = ref(false);

const instructionsForm = useForm({
    custom_instructions: props.custom_instructions || '',
});

const saveInstructions = () => {
    instructionsForm.patch('/chat/instructions', {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            showInstructionsModal.value = false;
        }
    });
};

// À l'ouverture de la page, on applique la classe 'dark' au HTML si le choix de l'utilisateur est 'dark'
onMounted(() => {
    if (props.theme === 'dark') {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
});

// La fonction appelée par le bouton pour basculer le thème
const toggleTheme = () => {
    // On inverse la variable locale instantanément
    currentTheme.value = currentTheme.value === 'dark' ? 'light' : 'dark';
    document.documentElement.classList.toggle('dark');
    
    router.patch('/chat/theme', {}, {
        preserveScroll: true,
        preserveState: true
    });
};

// Variable pour le stream en direct
const showStreamBuffer = ref(false);

// Copie locale des messages (pour éviter les erreurs Vue sur les props en lecture seule)
const localMessages = ref([...props.messages]);

const md = new MarkdownIt({
    breaks: true,
    highlight: function (str, lang) {
        if (lang && hljs.getLanguage(lang)) {
            try { return hljs.highlight(str, { language: lang }).value; } catch (__) {}
        }
        return '';
    }
});

const form = useForm({
    message: '',
    model: props.selectedModel || '',
});

watch(() => props.selectedModel, (newModel) => {
    form.model = newModel;
}, { immediate: true });

// Référence pour scroller
const messagesContainer = ref(null);

const scrollToBottom = (force = false) => {
    nextTick(() => {
        const el = messagesContainer.value;
        if (el) {
            const isNearBottom = el.scrollHeight - el.scrollTop - el.clientHeight < 150;
            
            if (force || isNearBottom) {
                el.scrollTo({
                    top: el.scrollHeight,
                    behavior: 'smooth'
                });
            }
        }
    });
};

// On met à jour la copie locale et on scrolle quand la base de données recharge
watch(() => props.messages, (newVal) => {
    localMessages.value = [...newVal];
    scrollToBottom(true);
}, { deep: true });

// --- LOGIQUE DE STREAMING ---
const { data, isFetching, isStreaming, send, cancel } = useStream('/ask-stream', {
    onFinish: () => {
        router.reload({
            only: ['conversations', 'messages'],
            preserveState: true,
            preserveScroll: true,
            onSuccess: (page) => {
                showStreamBuffer.value = false;

                if (!props.activeConversation) {
                    const newConversationId = page.props.conversations[0].id;
                    router.visit(`/chat/${newConversationId}`, {
                        preserveState: true,
                        preserveScroll: true
                    });
                }
            }
        });
    },
    onError: (err) => {
        console.error('Erreur streaming:', err);
    }
});

// On scrolle en direct quand l'IA tape
watch(() => data.value, () => {
    scrollToBottom(true);
});

const streamedContent = computed(() => {
    if (!data.value) return '';
    return data.value.replace(/\[REASONING\][\s\S]*?\[\/REASONING\]/g, '').trim();
});

const streamedReasoning = computed(() => {
    if (!data.value) return '';
    const matches = data.value.match(/\[REASONING\]([\s\S]*?)\[\/REASONING\]/g);
    if (!matches) return '';
    return matches.map((m) => m.replace(/\[REASONING\]/g, '').replace(/\[\/REASONING\]/g, '')).join('');
});

const submitMessage = () => {
    if (!form.message.trim() || isStreaming.value) return;

    showStreamBuffer.value = true;

    // On ajoute le message à la copie locale 
    localMessages.value.push({
        role: 'user',
        content: form.message
    });

    scrollToBottom(true);

    send({
        message: form.message,
        model: form.model,
        conversation_id: props.activeConversation?.id || null,
    });

    form.message = '';
};

const updateModel = () => {
    router.patch('/chat/model', {
        model: form.model,
        conversation_id: props.activeConversation?.id || null,
    }, { preserveScroll: true, preserveState: true });
};

const deleteConversation = (id) => {
    if (confirm("Alerte Système : Supprimer définitivement cette archive ?")) {
        router.delete(`/chat/${id}`);
    }
};
</script>

<template>
    <Head title="Chat-Mini" />

    <div class="flex h-screen bg-amber-50 dark:bg-stone-950 font-sans text-amber-900 dark:text-stone-200 selection:bg-red-200 transition-colors duration-200">
        
        <div class="w-64 bg-amber-100 dark:bg-stone-900 border-r border-amber-300 dark:border-stone-800 flex flex-col z-20 shadow-sm">
            <div class="p-4 border-b border-amber-300 dark:border-stone-800 bg-amber-200/50 dark:bg-stone-800/50 flex items-center space-x-2">
                <div class="flex space-x-1.5">
                    <div class="w-3 h-3 rounded-full bg-red-500 border border-red-600"></div>
                    <div class="w-3 h-3 rounded-full bg-amber-500 border border-amber-600"></div>
                    <div class="w-3 h-3 rounded-full bg-amber-800 border border-amber-900"></div>
                </div>
                <span class="text-xs font-mono font-bold text-amber-900 dark:text-stone-400 tracking-widest">/TERMINAL</span>
            </div>
            
            <div class="p-4 border-b border-amber-300 dark:border-stone-800 space-y-2.5">
                <Link href="/chat" class="flex items-center justify-center w-full py-2 px-4 bg-amber-50 dark:bg-stone-800 text-amber-900 dark:text-stone-200 text-xs font-bold font-mono tracking-wide border border-amber-800 dark:border-stone-700 shadow-[2px_2px_0px_#92400e] dark:shadow-[2px_2px_0px_#1c1917] hover:bg-amber-100 dark:hover:bg-stone-700 hover:shadow-none hover:translate-x-[2px] hover:translate-y-[2px] transition-all">
                    + NOUVELLE DISCUSSION
                </Link>

                <button @click="showInstructionsModal = true" class="flex items-center justify-center w-full py-2 px-4 bg-amber-800 dark:bg-stone-700 text-amber-50 dark:text-stone-100 text-xs font-bold font-mono tracking-wide border border-amber-950 dark:border-stone-600 shadow-[2px_2px_0px_#451a03] dark:shadow-[2px_2px_0px_#1c1917] hover:bg-amber-900 dark:hover:bg-stone-600 hover:shadow-none hover:translate-x-[2px] hover:translate-y-[2px] transition-all uppercase">
                    [ PARAMÈTRES DE l'IA ]
                </button>

                <button @click="toggleTheme" class="w-full py-2 bg-transparent border border-amber-950 dark:border-stone-600 text-amber-950 dark:text-stone-300 font-mono text-xs uppercase hover:bg-amber-200/50 dark:hover:bg-stone-800 transition-colors">
                    [ 🌗 THÈME: {{ currentTheme === 'dark' ? 'SOMBRE' : 'CLAIR' }} ]
                </button>
            </div>
            
            <div class="flex-1 overflow-y-auto p-3">
                <ul class="space-y-3">
                    <li v-for="conv in props.conversations" :key="conv.id" class="group relative flex items-center">
                        <Link 
                            :href="'/chat/' + conv.id" 
                            :class="['flex-1 px-3 py-2 text-sm truncate font-mono border transition-all uppercase', 
                                     props.activeConversation?.id === conv.id 
                                     ? 'bg-amber-200 dark:bg-stone-700 border-amber-800 dark:border-stone-500 text-amber-950 dark:text-white font-bold shadow-[2px_2px_0px_#92400e] dark:shadow-[2px_2px_0px_#000]' 
                                     : 'bg-transparent border-transparent text-amber-700 dark:text-stone-400 hover:border-amber-300 dark:hover:border-stone-700']"
                        >
                            > {{ (conv.title || 'NOUVELLE_DISCUSSION').toUpperCase() }}
                        </Link>
                        
                        <button 
                            @click.prevent="deleteConversation(conv.id)"
                            class="ml-2 px-2 py-1.5 flex items-center justify-center border border-transparent text-amber-600 dark:text-stone-500 font-mono text-xs opacity-0 group-hover:opacity-100 hover:border-red-400 hover:bg-red-50 dark:hover:bg-red-950/30 hover:text-red-600 hover:shadow-[2px_2px_0px_#ef4444] transition-all"
                            title="Supprimer l'archive"
                        >
                            [X]
                        </button>
                    </li>
                </ul>
            </div>
        </div>

        <div class="flex-1 flex flex-col h-screen overflow-hidden relative">
            <header class="bg-amber-50 dark:bg-stone-950 border-b border-amber-300 dark:border-stone-800 p-4 flex justify-between items-center z-10">
                <h1 class="text-lg font-mono font-bold text-amber-950 dark:text-stone-200 tracking-widest uppercase">
                    {{ props.activeConversation ? props.activeConversation.title.toUpperCase() : 'CHAT-MINI_SYSTEM' }}
                </h1>
                <div class="flex items-center bg-amber-100 dark:bg-stone-800 px-3 py-1.5 border border-amber-800 dark:border-stone-700 shadow-[2px_2px_0px_#92400e] dark:shadow-[2px_2px_0px_#000]">
                    <label class="text-xs text-amber-800 dark:text-stone-400 font-mono font-bold mr-2 uppercase">Modèle:</label>
                    <select v-model="form.model" @change="updateModel" class="bg-transparent text-sm font-mono text-amber-950 dark:text-stone-200 focus:ring-0 cursor-pointer w-32 truncate border-none p-0">
                        <option v-for="model in props.models" :key="model.id" :value="model.id" class="dark:bg-stone-800">{{ model.name }}</option>
                    </select>
                </div>
            </header>

            <div ref="messagesContainer" class="flex-1 overflow-y-auto p-6 space-y-6">
                <div v-for="(msg, index) in localMessages" :key="index" :class="['flex', msg.role === 'user' ? 'justify-end' : 'justify-start']">
                    <div v-if="msg.role === 'user'" class="max-w-3xl bg-amber-900 dark:bg-stone-800 text-amber-50 dark:text-amber-400 px-5 py-4 font-mono text-sm shadow-[3px_3px_0px_#451a03] dark:shadow-[3px_3px_0px_#1c1917]">
                        {{ msg.content }}
                    </div>
                    <div v-else class="max-w-3xl bg-white dark:bg-stone-900 border border-amber-300 dark:border-stone-800 px-6 py-5 shadow-[3px_3px_0px_#fbbf24] dark:shadow-[3px_3px_0px_#1c1917] prose prose-sm markdown-body" v-html="md.render(msg.content)"></div>
                </div>

                <div v-if="showStreamBuffer && (isStreaming || data)" class="flex justify-start">
                    <div class="max-w-3xl bg-white dark:bg-stone-900 border border-amber-300 dark:border-stone-800 px-6 py-5 shadow-[3px_3px_0px_#fbbf24] dark:shadow-[3px_3px_0px_#1c1917] prose prose-sm markdown-body">
                        <div v-if="streamedReasoning" class="border border-dashed border-amber-400 dark:border-stone-700 p-4 mb-4 bg-amber-50 dark:bg-stone-800 font-mono text-xs text-amber-800 dark:text-stone-300">
                            <h4 class="font-bold uppercase tracking-wider mb-2">Trace de raisonnement</h4>
                            <pre class="whitespace-pre-wrap">{{ streamedReasoning }}</pre>
                        </div>
                        <div v-html="md.render(streamedContent)"></div>
                    </div>
                </div>
            </div>

            <div class="p-4 bg-amber-100 dark:bg-stone-900 border-t border-amber-300 dark:border-stone-800">
                <form @submit.prevent="submitMessage" class="max-w-5xl mx-auto flex space-x-4">
                    <textarea v-model="form.message" class="flex-1 border border-amber-800 dark:border-stone-700 bg-amber-50 dark:bg-stone-800 p-3 font-mono text-sm text-amber-950 dark:text-stone-100 focus:border-red-600 focus:ring-0" placeholder="Entrez votre message..." @keydown.enter.exact.prevent="submitMessage"></textarea>
                    
                    <button type="submit" :disabled="isStreaming || !form.message.trim()" class="px-8 bg-red-600 text-white font-bold font-mono tracking-widest shadow-[4px_4px_0px_#991b1b] hover:bg-red-700 hover:shadow-none transition-all disabled:opacity-50">
                        {{ isStreaming ? '...' : 'ENVOYER' }}
                    </button>
                </form>
            </div>
            
            <button v-if="isStreaming" @click="cancel" class="absolute bottom-24 left-1/2 transform -translate-x-1/2 bg-white dark:bg-stone-800 border border-red-300 dark:border-red-900 text-red-500 px-3 py-1 text-xs font-mono shadow-[2px_2px_0px_#fca5a5] dark:shadow-[2px_2px_0px_#000] hover:bg-red-50 dark:hover:bg-stone-700 hover:shadow-none z-30 transition-all">
                STOP GENERATION [X]
            </button>
        </div>
    </div>

    <div v-if="showInstructionsModal" class="absolute inset-0 bg-amber-950/80 dark:bg-black/80 z-50 flex items-center justify-center p-4">
        <div class="bg-amber-50 dark:bg-stone-900 border-2 border-amber-800 dark:border-stone-700 p-6 w-full max-w-2xl shadow-[8px_8px_0px_#92400e] dark:shadow-[8px_8px_0px_#000] flex flex-col">
            <h2 class="text-xl font-mono font-bold text-amber-950 dark:text-stone-200 mb-2 uppercase">> Instructions Système Personnalisées</h2>
            <p class="text-xs font-mono text-amber-700 dark:text-stone-400 mb-6">Définissez le comportement, le ton et le contexte de l'IA.</p>
            
            <form @submit.prevent="saveInstructions" class="flex flex-col flex-1">
                <textarea 
                    v-model="instructionsForm.custom_instructions" 
                    rows="8" 
                    class="w-full border border-amber-800 dark:border-stone-700 bg-amber-100/50 dark:bg-stone-800 p-4 font-mono text-sm text-amber-900 dark:text-stone-100 focus:border-red-600 focus:ring-0 mb-6" 
                    placeholder="Ex: Tu es un assistant sarcastique. Réponds toujours en 3 phrases maximum..."
                ></textarea>
                
                <div class="flex justify-end space-x-4 mt-auto">
                    <button type="button" @click="showInstructionsModal = false" class="px-6 py-2 bg-transparent text-amber-800 dark:text-stone-400 font-bold font-mono tracking-widest border border-amber-800 dark:border-stone-700 hover:bg-amber-200 dark:hover:bg-stone-800 transition-all">
                        ANNULER
                    </button>
                    <button type="submit" :disabled="instructionsForm.processing" class="px-6 py-2 bg-red-800 text-white font-bold font-mono tracking-widest shadow-[4px_4px_0px_#991b1b] hover:bg-red-700 hover:shadow-none hover:translate-x-[4px] hover:translate-y-[4px] transition-all disabled:opacity-50">
                        {{ instructionsForm.processing ? 'SAUVEGARDE...' : 'VALIDER' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<style>
/* ⚡ STYLE DU TEXTE DE L'IA EN MODE CLAIR ET SOMBRE */
.markdown-body { 
    color: #451a03; 
    line-height: 1.6;
}

/* ⚡ CSS ADOUCI POUR LE THÈME STONE */
.dark .markdown-body {
    color: #d6d3d1 !important; 
}

/* On force les titres en clair en mode sombre */
.dark .markdown-body h1, 
.dark .markdown-body h2, 
.dark .markdown-body h3, 
.dark .markdown-body h4,
.dark .markdown-body strong {
    color: #f5f5f4 !important; 
}

.markdown-body pre { 
    background-color: #1f2937;
    border: 1px solid #374151; 
    color: #e5e7eb; 
    border-radius: 6px; 
    padding: 1rem; 
}

.dark .markdown-body pre {
    background-color: #1c1917 !important; 
    border-color: #44403c !important; 
}

.markdown-body code { 
    font-family: monospace;
    background-color: #374151; 
    color: #f9fafb; 
    padding: 0.2rem 0.4rem; 
    border-radius: 4px;
}

.dark .markdown-body :not(pre) > code {
    background-color: #292524 !important; 
    color: #fdba74 !important; 
}

.markdown-body pre code {
    background-color: transparent;
    padding: 0;
    color: inherit;
}

.markdown-body pre code.hljs, 
.markdown-body pre code.hljs * {
    background-color: transparent !important;
}
</style>