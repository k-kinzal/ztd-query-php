<?php

declare(strict_types=1);
require __DIR__ . '/vendor/autoload.php';
$s = (new MySqlMemory\Instance($argv[1]))->connect();
foreach (array_slice($argv, 2) as $q) {
    try {
        $r = $s->query($q);
        echo json_encode($r[0]->rows ?? $r), "\n";
    } catch (Throwable $e) {
        echo get_class($e), ': ', $e->getMessage(), "\n";
    }
}
