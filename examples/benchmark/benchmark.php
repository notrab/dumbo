<?php

require "vendor/autoload.php";

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;

$baseUrl = "http://localhost:8000";
$scenarios = [
    "simple" => ["method" => "GET", "path" => "/", "count" => 1000],
    "echo" => [
        "method" => "POST",
        "path" => "/echo",
        "count" => 500,
        "body" => json_encode(["test" => "data"]),
    ],
    "cpu" => ["method" => "GET", "path" => "/cpu", "count" => 100],
    "db" => ["method" => "GET", "path" => "/db/{id}", "count" => 500],
    "large" => ["method" => "GET", "path" => "/large", "count" => 200],
];
$concurrency = 50;

$client = new Client(["base_uri" => $baseUrl]);

function runScenario($client, $scenario, $concurrency)
{
    $requests = function ($total) use ($scenario) {
        for ($i = 0; $i < $total; $i++) {
            $path = str_replace("{id}", rand(1, 1000), $scenario["path"]);
            yield new Request(
                $scenario["method"],
                $path,
                [],
                $scenario["body"] ?? null
            );
        }
    };

    $startTime = microtime(true);

    $pool = new Pool($client, $requests($scenario["count"]), [
        "concurrency" => $concurrency,
        "fulfilled" => function ($response, $index) {
            //
        },
        "rejected" => function ($reason, $index) {
            echo "Request $index failed: " . $reason->getMessage() . "\n";
        },
    ]);

    $pool->promise()->wait();

    $endTime = microtime(true);
    $totalTime = $endTime - $startTime;
    $averageTime = $totalTime / $scenario["count"];

    return [
        "totalTime" => $totalTime,
        "averageTime" => $averageTime,
        "requestsPerSecond" => $scenario["count"] / $totalTime,
    ];
}

echo "Benchmark Results:\n";
echo "Concurrency: $concurrency\n\n";

foreach ($scenarios as $name => $scenario) {
    echo "Scenario: $name\n";
    echo "Method: {$scenario["method"]}, Path: {$scenario["path"]}, Requests: {$scenario["count"]}\n";

    $results = runScenario($client, $scenario, $concurrency);

    echo "Total Time: " .
        number_format($results["totalTime"], 4) .
        " seconds\n";
    echo "Average Time per Request: " .
        number_format($results["averageTime"] * 1000, 4) .
        " ms\n";
    echo "Requests per Second: " .
        number_format($results["requestsPerSecond"], 2) .
        "\n\n";
}
