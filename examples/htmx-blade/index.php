<?php

require __DIR__ . "/vendor/autoload.php";

use Dumbo\Dumbo;
use Jenssegers\Blade\Blade;
use Libsql\Database;

$app = new Dumbo();

$db = new Database(path: "file.db");

$conn = $db->connect();

$conn->execute("
    CREATE TABLE IF NOT EXISTS todos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        task TEXT NOT NULL,
        completed INTEGER NOT NULL DEFAULT 0
    )
");

$blade = new Blade(__DIR__ . "/views", __DIR__ . "/cache");

function render($blade, $view, $data = [])
{
    return $blade->make($view, $data)->render();
}

$app->get("/", function ($c) use ($blade, $conn) {
    $todos = $conn->query("SELECT * FROM todos ORDER BY id DESC")->fetchArray();
    $html = render($blade, "layout", [
        "content" => render($blade, "todo", ["todos" => $todos]),
    ]);
    return $c->html($html);
});

$app->post("/todos", function ($c) use ($blade, $conn) {
    $task = $c->req->body()["task"] ?? "";
    if (!empty($task)) {
        $conn->query("INSERT INTO todos (task) VALUES (?)", [$task]);
    }
    $todos = $conn->query("SELECT * FROM todos ORDER BY id DESC")->fetchArray();
    return $c->html(render($blade, "todo", ["todos" => $todos]));
});

$app->put("/todos/:id", function ($c) use ($blade, $conn) {
    $id = (int) $c->req->param("id");
    $conn->query("UPDATE todos SET completed = NOT completed WHERE id = ?", [
        $id,
    ]);
    $todos = $conn->query("SELECT * FROM todos ORDER BY id DESC")->fetchArray();
    return $c->html(render($blade, "todo", ["todos" => $todos]));
});

$app->delete("/todos/:id", function ($c) use ($blade, $conn) {
    $id = (int) $c->req->param("id");
    $conn->execute("DELETE FROM todos WHERE id = ?", [$id]);
    $todos = $conn->query("SELECT * FROM todos ORDER BY id DESC")->fetchArray();
    return $c->html(render($blade, "todo", ["todos" => $todos]));
});

$app->run();
