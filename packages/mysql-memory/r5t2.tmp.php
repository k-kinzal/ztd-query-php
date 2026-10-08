<?php

declare(strict_types=1);
require __DIR__ . '/vendor/autoload.php';
$port = $argv[1];
$rel = $argv[2];
$nat = new PDO('mysql:host=127.0.0.1;port=' . $port, 'root', 'root');
$g = [];
foreach ($nat->query('SHOW GLOBAL VARIABLES')->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) {
    $g[strtolower($k)] = (string) $v;
}
$s = (new MySqlMemory\Instance($rel, $g))->connect();
$s->query('CREATE DATABASE d');
$s->query('USE d');
foreach (array_slice($argv, 3) as $q) {
    try {
        $r = $s->query($q);
        echo json_encode($r[0]->rows ?? $r, JSON_INVALID_UTF8_SUBSTITUTE), "\n";
    } catch (Throwable $e) {
        echo get_class($e), ': ', $e->getMessage(), "\n";
    }
}
