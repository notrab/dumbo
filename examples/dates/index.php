<?php

require __DIR__ . "/vendor/autoload.php";

use Dumbo\Dumbo;

$app = new Dumbo();

// Dumbo doesn't ship date helpers; PHP's own date functions cover it
$app->get("/", function ($c) {
    $now = time();
    $nowNY = new DateTime("now", new DateTimeZone("America/New_York"));
    $formattedDate = date("Y-m-d H:i:s");

    $options = "";
    foreach (DateTimeZone::listIdentifiers() as $timezone) {
        $selected = $timezone === "Europe/London" ? " selected" : "";
        $options .= "<option{$selected}>{$timezone}</option>";
    }

    $months = "";
    foreach (range(1, 12) as $month) {
        $months .= "<li>" . date("F", mktime(0, 0, 0, $month, 1)) . "</li>";
    }

    $days = "";
    foreach (range(0, 6) as $day) {
        $days .= "<li>" . date("D", strtotime("Sunday +{$day} days")) . "</li>";
    }

    return $c->html("
        <h1>Dates Example</h1>
        <p>Current timestamp: {$now}</p>
        <p>Time in New York: {$nowNY->format("H:i")}</p>
        <p>Formatted date: {$formattedDate}</p>
        <h2>Timezone Select:</h2>
        <select name=\"timezone\">{$options}</select>
        <h2>Months:</h2>
        <ul>{$months}</ul>
        <h2>Days (abbreviated):</h2>
        <ul>{$days}</ul>
    ");
});

$app->run();
