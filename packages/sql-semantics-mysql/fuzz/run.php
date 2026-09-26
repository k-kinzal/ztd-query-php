<?php

/**
 * Streams PHP-Fuzzer output and returns failure for findings, including corpus crashes.
 * Usage: php fuzz/run.php mysql|pg|sqlite [PHP-Fuzzer options]
 */

declare(strict_types=1);

$database = $argv[1] ?? '';
if (!in_array($database, ['mysql', 'pg', 'sqlite'], true)) {
    fwrite(STDERR, "Usage: php fuzz/run.php mysql|pg|sqlite [PHP-Fuzzer options]\n");
    exit(2);
}
$command = [
    PHP_BINARY, '-d', 'memory_limit=-1', 'vendor/bin/php-fuzzer', 'fuzz',
    'fuzz/fuzz_' . $database . '_roundtrip.php', 'fuzz/corpus/' . $database . '/',
    '--timeout=60', ...array_slice($argv, 2),
];
$process = proc_open($command, [0 => STDIN, 1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes, dirname(__DIR__));
if (!is_resource($process)) {
    throw new RuntimeException('Cannot start PHP-Fuzzer');
}
$finding = false;
while (($line = fgets($pipes[1])) !== false) {
    fwrite(STDOUT, $line);
    $finding = $finding || preg_match('/(?:^|CORPUS )(?:CRASH|TIMEOUT) in /', $line) === 1;
}
fclose($pipes[1]);
$status = proc_close($process);
exit($finding || $status !== 0 ? 1 : 0);
