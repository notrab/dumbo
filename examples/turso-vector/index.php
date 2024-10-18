<?php

require __DIR__ . "/vendor/autoload.php";

use Dumbo\Dumbo;
use Jenssegers\Blade\Blade;
use Libsql\Database;

$app = new Dumbo();

$db = new Database(path: "file.db");
$conn = $db->connect();

initDatabase($conn);

$blade = new Blade(__DIR__ . "/views", __DIR__ . "/cache");

function render($blade, $view, $data = [])
{
    return $blade->make($view, $data)->render();
}

$app->get("/", function ($c) use ($blade, $conn) {
    $movies = $conn
        ->query("SELECT * FROM movies ORDER BY year DESC")
        ->fetchArray();
    $content = render($blade, "partials.movie-list", ["movies" => $movies]);

    return $c->html(render($blade, "layout", ["content" => $content]));
});

$app->get("/movie/:id", function ($c) use ($blade, $conn) {
    $id = intval($c->req->param("id"));

    $result = $conn
        ->query(
            "
        WITH movie AS (
            SELECT id, title, year, genres, embedding
            FROM movies
            WHERE id = ?
        )
        SELECT
            m.id,
            m.title,
            m.year,
            m.genres,
            movie.title AS current_movie_title,
            movie.year AS current_movie_year,
            movie.genres AS current_movie_genres
        FROM
            movie,
            vector_top_k('movies_idx', (SELECT embedding FROM movie), 4) AS v
        JOIN movies m ON m.rowid = v.id
        WHERE m.id != movie.id
        LIMIT 4
    ",
            [$id]
        )
        ->fetchArray();

    if (empty($result)) {
        return $c->json(["error" => "Movie not found"], 404);
    }

    $movie = [
        "id" => $id,
        "title" => $result[0]["current_movie_title"],
        "year" => $result[0]["current_movie_year"],
        "genres" => $result[0]["current_movie_genres"],
    ];

    $similar_movies = array_map(function ($row) {
        return [
            "id" => $row["id"],
            "title" => $row["title"],
            "year" => $row["year"],
            "genres" => $row["genres"],
        ];
    }, $result);

    $content = render($blade, "partials.movie-details", [
        "movie" => $movie,
        "similar_movies" => $similar_movies,
    ]);

    return $c->html(render($blade, "layout", ["content" => $content]));
});

$app->run();

function initDatabase($conn)
{
    $conn->executeBatch("
        DROP TABLE IF EXISTS movies;
        CREATE TABLE movies (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, year INT, genres TEXT, embedding F32_BLOB(3));
        CREATE INDEX movies_idx ON movies (libsql_vector_idx(embedding));
        INSERT INTO movies (title, year, genres, embedding) VALUES
            ('Napoleon', 2023, '[\"Historical\", \"Drama\", \"War\"]', vector32('[1,2,3]')),
            ('Black Hawk Down', 2001, '[\"War\", \"Action\", \"Drama\"]', vector32('[10,11,12]')),
            ('Gladiator', 2000, '[\"Action\", \"Adventure\", \"Drama\"]', vector32('[7,8,9]')),
            ('Blade Runner', 1982, '[\"Sci-Fi\", \"Thriller\", \"Drama\"]', vector32('[4,5,6]')),
            ('Inception', 2010, '[\"Sci-Fi\", \"Action\", \"Thriller\"]', vector32('[2,3,4]')),
            ('The Matrix', 1999, '[\"Sci-Fi\", \"Action\"]', vector32('[3,4,5]')),
            ('Interstellar', 2014, '[\"Sci-Fi\", \"Drama\"]', vector32('[5,6,7]')),
            ('The Terminator', 1984, '[\"Sci-Fi\", \"Action\"]', vector32('[6,7,8]')),
            ('Avatar', 2009, '[\"Sci-Fi\", \"Action\", \"Adventure\"]', vector32('[8,9,10]')),
            ('Star Wars: A New Hope', 1977, '[\"Sci-Fi\", \"Adventure\"]', vector32('[9,10,11]'));
    ");
}
