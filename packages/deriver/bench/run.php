<?php

declare(strict_types=1);

$measurements = [];
$sizes = array_slice($argv, 1);
$sizes = $sizes === [] ? ['1000', '10000', '100000'] : $sizes;
foreach ($sizes as $size) {
    if (!ctype_digit($size) || (int) $size < 40 || (int) $size > 100000) {
        throw new InvalidArgumentException('Benchmark sizes must be integers between 40 and 100000.');
    }
    $process = proc_open([PHP_BINARY, '-d', 'memory_limit=2G', '-d', 'xdebug.mode=off', __DIR__ . '/measure.php', $size], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start isolated benchmark process.');
    }
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0 || $output === false) {
        throw new RuntimeException('Benchmark failed: ' . $error);
    }
    $measurements[] = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
}
echo json_encode(['schemaVersion' => 1, 'runs' => $measurements], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
