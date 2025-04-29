<?php

require __DIR__ . '/vendor/autoload.php';

use Dumbo\Dumbo;
use Dumbo\Helpers\DatabaseHelper;


$app = new Dumbo();

$app->use(function ($container) {
    $dbHelper = new DatabaseHelper('localhost', 'your_db', 'username', 'password');
    $container->set('db', $dbHelper);
});

$app->get('/', function ($container) {
    $db = $container->get('db');
    $queryBuilder = $db->table('your_table');

    try {
        $results = $queryBuilder->select('*')->get();
        foreach ($results as $result) {
            print_r($result);
        }
    } catch (Exception $e) {
        error_log('Database error: ' . $e->getMessage());
    }
});
$app->run();
