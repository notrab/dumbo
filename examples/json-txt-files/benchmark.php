<?php

require_once "database.php";

$schema = [
    "users" => [
        "name" => "string",
        "email" => "string",
        "age" => "int",
    ],
    "posts" => [
        "title" => "string",
        "content" => "string",
        "user_id" => "string",
    ],
    "comments" => [
        "content" => "string",
        "post_id" => "string",
        "user_id" => "string",
    ],
];
$db = new Database(__DIR__ . "/data", $schema);

function benchmark($description, $func)
{
    $start = microtime(true);
    $result = $func();
    $end = microtime(true);
    $time = ($end - $start) * 1000; // ms
    echo sprintf("%s: %.2f ms\n", $description, $time);
    return $result;
}

$numRecords = 100; // 100,000 records
benchmark("Inserting $numRecords users", function () use ($db, $numRecords) {
    for ($i = 0; $i < $numRecords; $i++) {
        $db->insert("users", [
            "name" => "User $i",
            "email" => "user$i@example.com",
            "age" => rand(18, 80),
        ]);
    }
});

benchmark("Inserting $numRecords posts", function () use ($db, $numRecords) {
    for ($i = 0; $i < $numRecords; $i++) {
        $db->insert("posts", [
            "title" => "Post $i",
            "content" => "Content of post $i",
            "user_id" => rand(1, $numRecords),
        ]);
    }
});

benchmark("Inserting $numRecords comments", function () use ($db, $numRecords) {
    for ($i = 0; $i < $numRecords; $i++) {
        $db->insert("comments", [
            "content" => "Comment $i",
            "post_id" => rand(1, $numRecords),
            "user_id" => rand(1, $numRecords),
        ]);
    }
});

// Select all users (first run, no cache)
benchmark("Select all users (no cache)", function () use ($db) {
    return $db->select("users");
});

// Select all users (second run, should use cache)
benchmark("Select all users (with cache)", function () use ($db) {
    return $db->select("users");
});

// Select single user by ID
$randomUserId = rand(1, $numRecords);
benchmark("Select single user (no cache)", function () use (
    $db,
    $randomUserId
) {
    return $db->select("users", ["id" => $randomUserId]);
});

// Select single user by ID (second run, should use cache)
benchmark("Select single user (with cache)", function () use (
    $db,
    $randomUserId
) {
    return $db->select("users", ["id" => $randomUserId]);
});

// Update a user
benchmark("Update user", function () use ($db, $randomUserId) {
    $db->update("users", $randomUserId, ["name" => "Updated User"]);
});

// Select updated user (should not use cache due to update)
benchmark("Select updated user (no cache)", function () use (
    $db,
    $randomUserId
) {
    return $db->select("users", ["id" => $randomUserId]);
});

// Delete a user
benchmark("Delete user", function () use ($db, $randomUserId) {
    $db->delete("users", $randomUserId);
});

// Complex query
benchmark("Complex query - users with posts and comments", function () use (
    $db
) {
    $users = $db->select("users", ["age" => [">=", 30]]);
    $userIds = array_column($users, "id");
    $posts = $db->select("posts", ["user_id" => ["in", $userIds]]);
    $postIds = array_column($posts, "id");
    return $db->select("comments", ["post_id" => ["in", $postIds]]);
});

// Count records
benchmark("Count users", function () use ($db) {
    return $db->count("users");
});

benchmark("Count posts", function () use ($db) {
    return $db->count("posts");
});

benchmark("Count comments", function () use ($db) {
    return $db->count("comments");
});

// Memory usage
echo "Peak memory usage: " .
    memory_get_peak_usage(true) / 1024 / 1024 .
    " MB\n";
