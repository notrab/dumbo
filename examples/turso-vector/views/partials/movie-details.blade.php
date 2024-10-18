<div>
    <h2 class="text-2xl font-bold mb-3">{{ $movie['title'] }} ({{ $movie['year'] }})</h2>
    <p class="mb-2">Genres: {{ implode(', ', json_decode($movie['genres'])) }}</p>
    <h3 class="text-xl font-semibold mb-2">Similar Movies:</h3>
    <ul class="space-y-4 mb-4">
        @foreach ($similar_movies as $similar)
            <li class="bg-gray-100 p-2 rounded">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-gray-800">
                        {{ $similar['title'] }} ({{ $similar['year'] }})
                    </span>
                    <a href="/movie/{{ $similar['id'] }}"
                       class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-1 px-2 rounded text-sm">
                        Details
                    </a>
                </div>
                <p class="text-sm mb-1">Genres: {{ implode(', ', json_decode($similar['genres'])) }}</p>
            </li>
        @endforeach
    </ul>
    <a href="/"
       class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
        Back to List
    </a>
</div>
