<ul class="space-y-2">
    @foreach ($movies as $movie)
        <li class="flex items-center justify-between bg-gray-100 p-2 rounded">
            <div>
                <span class="text-gray-800">
                    {{ $movie['title'] }} ({{ $movie['year'] }})
                </span>
                <p class="text-sm text-gray-600">
                    Genres: {{ implode(', ', json_decode($movie['genres'])) }}
                </p>
            </div>
            <a href="/movie/{{ $movie['id'] }}"
               class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-1 px-2 rounded text-sm">
                Details
            </a>
        </li>
    @endforeach
</ul>
