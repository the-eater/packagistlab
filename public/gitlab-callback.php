<?php

include __DIR__ . '/../vendor/autoload.php';

$config = include(__DIR__ . '/../config/config.php');

if ((!isset($_GET['secret']) || $_GET['secret'] !== $config['secret']) &&
    (!isset($argv[1]) || $argv[1] !== $config['secret'])) {
    http_response_code(404);
    exit();
}

include __DIR__ . "/../src/Indexer.php";

$index = new Indexer($config['gitlab-uri'], $config['api-key']);
$packages = $index->indexAll();

$json = \GuzzleHttp\json_encode([
    'packages' => $packages,
]);

file_put_contents(__DIR__. '/packages.json', $json);