<?php

require __DIR__ . "/vendor/autoload.php";
require __DIR__ . "/database.php";

use Dumbo\Dumbo;

$app = new Dumbo();

$schema = [
    "users" => [
        "name" => "string",
        "email" => "string",
        "age" => "int",
    ],
    "authors" => [
        "name" => "string",
        "email" => "string",
    ],
    "posts" => [
        "title" => "string",
        "content" => "string",
        "author_id" => "string",
    ],
    "comments" => [
        "content" => "string",
        "post_id" => "string",
        "author_id" => "string",
    ],
];

$db = new Database(__DIR__ . "/data", $schema);

$app->post("/authors", function ($c) use ($db) {
    $data = $c->req->body();
    try {
        $id = $db->insert("authors", $data);
        return $c->json(["id" => $id], 201);
    } catch (Exception $e) {
        return $c->json(["error" => $e->getMessage()], 400);
    }
});

$app->get("/authors", function ($c) use ($db) {
    $authors = $db->select("users");
    return $c->json($authors);
});

$app->get("/authors/:id", function ($c) use ($db) {
    $id = $c->req->param("id");
    $author = $db->select("authors", ["id" => $id]);
    if (empty($author)) {
        return $c->json(["error" => "Author not found"], 404);
    }
    return $c->json($author[0]);
});

$app->post("/posts", function ($c) use ($db) {
    $data = $c->req->body();
    try {
        $id = $db->insert("posts", $data);
        return $c->json(["id" => $id], 201);
    } catch (Exception $e) {
        return $c->json(["error" => $e->getMessage()], 400);
    }
});

$app->get("/posts", function ($c) use ($db) {
    $posts = $db->select("posts");
    return $c->json($posts);
});

$app->get("/posts/:id", function ($c) use ($db) {
    $id = $c->req->param("id");
    $post = $db->select("posts", ["id" => $id]);
    if (empty($post)) {
        return $c->json(["error" => "Post not found"], 404);
    }
    return $c->json($post[0]);
});

$app->post("/comments", function ($c) use ($db) {
    $data = $c->req->body();
    try {
        $id = $db->insert("comments", $data);
        return $c->json(["id" => $id], 201);
    } catch (Exception $e) {
        return $c->json(["error" => $e->getMessage()], 400);
    }
});

$app->get("/comments", function ($c) use ($db) {
    $comments = $db->select("comments");
    return $c->json($comments);
});

$app->get("/comments/:id", function ($c) use ($db) {
    $id = $c->req->param("id");
    $comment = $db->select("comments", ["id" => $id]);
    if (empty($comment)) {
        return $c->json(["error" => "Comment not found"], 404);
    }
    return $c->json($comment[0]);
});

// Get comments for a specific post
$app->get("/posts/:id/comments", function ($c) use ($db) {
    $postId = $c->req->param("id");
    $comments = $db->select("comments", ["post_id" => $postId]);
    return $c->json($comments);
});

$app->post("/rebuild-index/:table", function ($c) use ($db) {
    $tableName = $c->req->param("table");
    try {
        $db->rebuildIndex($tableName);
        return $c->json(["message" => "Index rebuilt for table $tableName"]);
    } catch (Exception $e) {
        return $c->json(["error" => $e->getMessage()], 400);
    }
});

$app->get("/count/:table", function ($c) use ($db) {
    $tableName = $c->req->param("table");
    $conditions = $c->req->query() ?: [];

    try {
        $count = $db->count($tableName, $conditions);
        return $c->json(["count" => $count]);
    } catch (Exception $e) {
        return $c->json(["error" => $e->getMessage()], 400);
    }
});

$app->run();
