# 🤖 Chat-Mini : Assistant IA Minimaliste

Chat-Mini est une application web de chat interactive, légère et épurée, conçue pour offrir une expérience utilisateur simple et efficace. Elle permet d'interagir avec différents modèles d'IA en temps réel, avec une gestion complète des historiques de conversations.

## 🚀 Fonctionnalités principales

*   **Streaming en temps réel :** Réponses générées caractère par caractère pour une fluidité maximale.
*   **Gestion des conversations :** Historique sauvegardé, modification des titres et suppression facilitée.
*   **Personnalisation système :** Définition d'instructions système (system prompts) pour orienter le comportement de l'IA.
*   **Thème adaptatif :** Bascule entre mode clair et mode sombre, avec persistance en base de données.
*   **Multi-modèles :** Sélection dynamique du modèle d'IA utilisé pour chaque discussion.
*   **Interface rétro :** Design typographique inspiré des terminaux classiques avec une palette "Ambre".

## 🛠️ Stack Technique

*   **Framework :** [Laravel 11](https://laravel.com/)
*   **Frontend :** [Vue.js 3](https://vuejs.org/) avec [Inertia.js](https://inertiajs.com/)
*   **Styling :** [Tailwind CSS v4](https://tailwindcss.com/)
*   **Streaming :** Laravel Stream
*   **Markdown :** Markdown-it avec coloration syntaxique (Highlight.js)
*   **Base de données :** MySQL

## 🏗️ Architecture des données

Le projet repose sur une structure relationnelle robuste articulée autour de trois entités clés :

1.  **Utilisateur :** Gestion de l'authentification et des préférences (thème, instructions).
2.  **Conversation :** Conteneur logique liant un utilisateur à ses échanges.
3.  **Message :** Enregistrement de chaque tour de parole (rôle et contenu).

*(Voir le diagramme de classes joint au rapport pour le détail des relations).*

