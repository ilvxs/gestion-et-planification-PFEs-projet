<x-layout title="Planning interactif">
    <span class="step-badge">Planning interactif</span>

    <h1 class="mb-3">Visualisation interactive du planning</h1>

    <p class="text-muted">
        Cette page permet de consulter le planning généré avec recherche et filtres dynamiques sans recharger la page.
    </p>

    <div id="planning-viewer"></div>

    @viteReactRefresh
    @vite(['resources/js/app.jsx'])
</x-layout>