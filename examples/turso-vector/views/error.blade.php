<div>
    <h2 class="text-2xl font-bold mb-3 text-red-500">Error</h2>
    <p class="mb-4">{{ $message }}</p>
    <button hx-get="/"
            hx-target="#content"
            hx-swap="innerHTML"
            class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
        Back to List
    </button>
</div>
