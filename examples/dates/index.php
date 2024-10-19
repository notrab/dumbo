<?php

require __DIR__ . "/vendor/autoload.php";

use Dumbo\Dumbo;
use Dumbo\Helpers\DateHelper;

$app = new Dumbo();

$app->get("/", function ($c) {
    $now = DateHelper::now();
    $nowNY = DateHelper::now("America/New_York");
    $formattedDate = DateHelper::format("Y-m-d H:i:s");
    $timezoneSelect = DateHelper::timezoneSelect(
        "custom-select",
        "Europe/London"
    );
    $months = DateHelper::getMonthNames();
    $days = DateHelper::getDayNames(true);

    $html = "
        <h1>DateHelper Example</h1>
        <p>Current timestamp: {$now}</p>
        <p>Current timestamp in New York: {$nowNY}</p>
        <p>Formatted date: {$formattedDate}</p>
        <h2>Timezone Select:</h2>
        {$timezoneSelect}
        <h2>Months:</h2>
        <ul>
    ";

    foreach ($months as $month) {
        $html .= "<li>{$month}</li>";
    }

    $html .= "
        </ul>
        <h2>Days (abbreviated):</h2>
        <ul>
    ";

    foreach ($days as $day) {
        $html .= "<li>{$day}</li>";
    }

    $html .= "</ul>";

    return $c->html($html);
});

$app->run();
