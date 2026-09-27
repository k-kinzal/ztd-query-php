<?php

declare(strict_types=1);

$maxRuns = $argv[1] ?? '100';
if (preg_match('/^[1-9][0-9]{0,7}$/D', $maxRuns) !== 1) {
    throw new InvalidArgumentException('The run count must be between 1 and 99999999.');
}
$directory = dirname(__DIR__) . '/build/fuzz';
if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
    throw new RuntimeException('Cannot create the fuzz report directory.');
}
$corpusDirectory = dirname(__DIR__) . '/build/fuzz/corpus';
if (!is_dir($corpusDirectory) && !mkdir($corpusDirectory, 0777, true) && !is_dir($corpusDirectory)) {
    throw new RuntimeException('Cannot create the fuzz corpus directory.');
}
foreach (glob(dirname(__DIR__) . '/tests/Fixtures/Fuzz/*') ?: [] as $seed) {
    $target = $corpusDirectory . '/' . basename($seed);
    if (!is_file($target) && !copy($seed, $target)) {
        throw new RuntimeException('Cannot copy a fuzz seed.');
    }
}

$log = $directory . '/derive.log';
$pipes = [];
$process = proc_open([PHP_BINARY, dirname(__DIR__) . '/vendor/bin/php-fuzzer', 'fuzz', __DIR__ . '/derive.php', dirname(__DIR__) . '/build/fuzz/corpus', '--max-runs=' . $maxRuns], [0 => ['file', '/dev/null', 'r'], 1 => ['file', $log, 'w'], 2 => ['redirect', 1]], $pipes);
if (!is_resource($process)) {
    throw new RuntimeException('Cannot start the fuzz campaign.');
}
$status = proc_close($process);
$output = file_get_contents($log);
if ($output === false) {
    throw new RuntimeException('Cannot read the fuzz report.');
}
echo $output;
exit($status !== 0 || preg_match('/(?:CRASH|INSTRUMENTATION BROKEN|PARSE ERROR)/', $output) === 1 ? 1 : 0);
