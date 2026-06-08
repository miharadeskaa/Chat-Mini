<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, onUpdated, nextTick, watch } from 'vue';
import MarkdownIt from 'markdown-it';
import hljs from 'highlight.js';
import 'highlight.js/styles/github-dark.css';

const props = defineProps({
    conversations: { type: Array, default: () => [] },
    activeConversation: { type: Object, default: null },
    messages: { type: Array, default: () => [] },
    models: { type: Array, default: () => [] },
    selectedModel: { type: String, default: '' },
    flash: { type: Object, default: () => ({}) },
});

// Configuration du Markdown
const md = new MarkdownIt({
    highlight: function (str, lang) {
        if (lang && hljs.getLanguage(lang)) {
            try { return hljs.highlight(str, { language: lang }).value; } catch (__) {}
        }
        return '';
    }
});

// Formulaire pour le message
const form = useForm({
    message: '',
    model: props.selectedModel || '',
});

// Synchronisation du formulaire avec le modèle renvoyé par le serveur
watch(() => props.selectedModel, (newModel) => {
    form.model = newModel;
}, { immediate: true });

// Référence pour scroller automatiquement
const messagesContainer = ref(null);

onUpdated(() => {
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
        }
    });
});

// Fonction pour envoyer le message
const submitMessage = () => {
    if (!form.message.trim()) return;
    
    const url = props.activeConversation 
        ? '/chat/' + props.activeConversation.id 
        : '/chat';

    form.post(url, {
        preserveScroll: true,
        onSuccess: () => form.reset('message'),
    });
};

// Fonction pour sauvegarder le changement de modèle
const updateModel = () => {
    router.patch('/chat/model', {
        model: form.model,
        conversation_id: props.activeConversation?.id || null,
    }, { 
        preserveScroll: true,
        preserveState: true 
    });
};
</script>

<template>
    <Head title="Chat-Mini" />

    <!-- Fond très clair (bleu/cyan pâle) typique de ce style -->
    <div class="flex h-screen bg-[#f4fbff] font-sans text-slate-700 selection:bg-pink-200">
        
        <!-- SIDEBAR : Fond blanc, bordure cyan fine -->
        <div class="w-64 bg-white border-r border-cyan-300 flex flex-col z-20 shadow-sm">
            
            <!-- EN-TÊTE SIDEBAR -->
            <div class="p-4 border-b border-cyan-300 bg-cyan-50/50 flex items-center space-x-2">
                <!-- Petits points style fenêtre OS -->
                <div class="flex space-x-1.5">
                    <div class="w-3 h-3 rounded-full bg-pink-400 border border-pink-500"></div>
                    <div class="w-3 h-3 rounded-full bg-yellow-400 border border-yellow-500"></div>
                    <div class="w-3 h-3 rounded-full bg-green-400 border border-green-500"></div>
                </div>
                <span class="text-xs font-mono font-bold text-cyan-700 tracking-widest">/MENU</span>
            </div>
            
            <!-- BOUTON NOUVEAU : Style filaire (outline) -->
            <div class="p-4 border-b border-cyan-100">
                <Link href="/chat" class="flex items-center justify-center w-full py-2 px-4 bg-white text-cyan-600 text-xs font-bold font-mono tracking-wide border border-cyan-400 shadow-[2px_2px_0px_#22d3ee] hover:bg-cyan-50 hover:shadow-none hover:translate-x-[2px] hover:translate-y-[2px] transition-all">
                    + NOUVELLE DISCUSSION
                </Link>
            </div>
            
            <div class="flex-1 overflow-y-auto p-3">
                <ul class="space-y-2">
                    <li v-for="conv in props.conversations" :key="conv.id">
                        <!-- CONVERSATIONS -->
                        <Link 
                            :href="'/chat/' + conv.id" 
                            :class="['block px-3 py-2 text-sm truncate font-mono border transition-all', 
                                     props.activeConversation?.id === conv.id 
                                     ? 'bg-cyan-50 border-cyan-400 text-cyan-800 font-bold shadow-[2px_2px_0px_#22d3ee]' 
                                     : 'bg-white border-transparent text-slate-500 hover:border-cyan-200']"
                        >
                            > {{ conv.title || 'sans_titre.log' }}
                        </Link>
                    </li>
                </ul>
            </div>
        </div>

        <!-- MAIN AREA -->
        <div class="flex-1 flex flex-col h-screen overflow-hidden relative">
            
            <!-- HEADER PRINCIPAL -->
            <header class="bg-white border-b border-cyan-300 p-4 flex justify-between items-center z-10 shadow-sm">
                
                <div class="flex items-center space-x-3">
                    <h1 class="text-lg font-mono font-bold text-cyan-800 tracking-widest">
                        {{ props.activeConversation ? props.activeConversation.title : 'CHAT-MINI' }}
                    </h1>
                    <span class="px-2 py-0.5 bg-yellow-100 text-yellow-700 border border-yellow-300 text-[10px] font-mono font-bold uppercase">En ligne</span>
                </div>
                
                <!-- SELECTEUR DE MODELE -->
                <div class="flex items-center bg-[#f4fbff] px-3 py-1.5 border border-cyan-300 shadow-[2px_2px_0px_#22d3ee]">
                    <label for="model" class="text-xs text-cyan-600 font-mono font-bold mr-2 uppercase">Modèle:</label>
                    <select id="model" v-model="form.model" @change="updateModel" class="block w-48 bg-transparent border-none text-slate-700 focus:ring-0 text-sm font-mono p-0 cursor-pointer">
                        <option v-for="model in props.models" :key="model.id" :value="model.id">
                            {{ model.name }}
                        </option>
                    </select>
                </div>
            </header>

            <div v-if="props.flash?.error" class="bg-pink-100 text-pink-700 font-mono text-xs px-4 py-2 border-b border-pink-300">
                [ERR] {{ props.flash.error }}
            </div>

            <!-- MESSAGES CONTAINER -->
            <div ref="messagesContainer" class="flex-1 overflow-y-auto p-6 space-y-6">
                
                <div v-if="props.messages.length === 0" class="h-full flex flex-col items-center justify-center text-cyan-600/50">
                    <div class="text-4xl mb-4">⚡</div>
                    <p class="font-mono text-sm tracking-widest uppercase">Système prêt. En attente de saisie.</p>
                </div>

                <div v-for="(msg, index) in props.messages" :key="index" :class="['flex', msg.role === 'user' ? 'justify-end' : 'justify-start']">
                    
                    <!-- USER MESSAGE : Fond cyan clair, bordure cyan -->
                    <div v-if="msg.role === 'user'" class="max-w-3xl bg-cyan-50 border border-cyan-300 text-slate-800 px-5 py-4 shadow-[3px_3px_0px_#22d3ee] whitespace-pre-wrap relative">
                        <div class="absolute -top-2.5 right-4 bg-cyan-100 border border-cyan-300 px-2 py-0.5 text-[10px] font-mono font-bold text-cyan-700">UTILISATEUR</div>
                        {{ msg.content }}
                    </div>
                    
                    <!-- AI MESSAGE : Fond blanc, bordure grise/violette -->
                    <div v-else class="max-w-3xl bg-white border border-purple-200 text-slate-700 px-6 py-5 shadow-[3px_3px_0px_#e9d5ff] prose prose-sm prose-slate max-w-none relative mt-2">
                        <div class="absolute -top-2.5 left-4 bg-purple-50 border border-purple-200 px-2 py-0.5 text-[10px] font-mono font-bold text-purple-700">SYSTÈME</div>
                        <div v-html="md.render(msg.content)"></div>
                    </div>
                </div>
                
                <!-- LOADER -->
                <div v-if="form.processing" class="flex justify-start mt-2">
                    <div class="bg-white border border-yellow-300 px-5 py-3 shadow-[3px_3px_0px_#fde047] flex items-center space-x-3 relative">
                        <div class="absolute -top-2.5 left-4 bg-yellow-50 border border-yellow-300 px-2 py-0.5 text-[10px] font-mono font-bold text-yellow-700">STATUT</div>
                        <span class="font-mono text-xs text-yellow-700">Génération</span>
                        <div class="flex space-x-1">
                            <div class="w-1.5 h-1.5 bg-yellow-500 rounded-full animate-pulse"></div>
                            <div class="w-1.5 h-1.5 bg-yellow-500 rounded-full animate-pulse" style="animation-delay: 0.2s"></div>
                            <div class="w-1.5 h-1.5 bg-yellow-500 rounded-full animate-pulse" style="animation-delay: 0.4s"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- INPUT AREA : Le bouton rose caractéristique -->
            <div class="p-4 bg-white border-t border-cyan-300 shadow-sm">
                <form @submit.prevent="submitMessage" class="max-w-5xl mx-auto flex space-x-4">
                    <div class="relative flex-1">
                        <div class="absolute top-4 left-3 text-cyan-400 font-mono text-sm">></div>
                        <textarea 
                            v-model="form.message" 
                            rows="1" 
                            placeholder="Entrez votre message..." 
                            class="block w-full bg-[#f4fbff] text-slate-700 border border-cyan-300 focus:border-cyan-500 focus:ring-0 resize-none py-3 pl-8 pr-4 font-mono text-sm shadow-[inset_1px_1px_3px_rgba(0,0,0,0.05)]"
                            @keydown.enter.exact.prevent="submitMessage"
                            style="max-height: 150px; overflow-y: auto;"
                        ></textarea>
                    </div>
                    
                    <!-- Bouton Send : Le fameux bouton rose -->
                    <button 
                        type="submit" 
                        :disabled="form.processing || !form.message.trim()" 
                        class="flex items-center justify-center px-8 bg-pink-500 text-white font-mono font-bold text-sm tracking-widest border border-pink-600 shadow-[3px_3px_0px_#be185d] hover:bg-pink-400 hover:shadow-none hover:translate-x-[3px] hover:translate-y-[3px] focus:outline-none disabled:opacity-50 disabled:transform-none disabled:shadow-[3px_3px_0px_#be185d] transition-all"
                    >
                        ENVOYER
                    </button>
                </form>
            </div>

        </div>
    </div>
</template>