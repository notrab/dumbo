<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movie Recommender</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen py-6 flex flex-col justify-center sm:py-12">
    <div class="relative py-3 max-w-xl mx-auto w-full">
        <div class="relative px-4 py-10 bg-white shadow-lg sm:rounded-3xl sm:p-20">
            <h1 class="text-3xl font-bold mb-5 text-center text-gray-800">Movie Recommender</h1>
            {!! $content !!}
        </div>
    </div>
</body>
</html>
