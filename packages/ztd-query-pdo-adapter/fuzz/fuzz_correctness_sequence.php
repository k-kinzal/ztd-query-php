<?php

declare(strict_types=1);

register_shutdown_function(static function (): void {
    if (function_exists('pcntl_alarm')) {
        pcntl_alarm(0);
    }
});

use Fuzz\Correctness\CorrectnessHarness;

[$host, $port] = Fuzz\Container\DatabaseEndpoint::mysql();

$rawPdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", 'root', 'root', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$db = 'fuzz_' . bin2hex(random_bytes(4));
$rawPdo->exec("CREATE DATABASE `$db`");

$target = new Fuzz\Correctness\SequenceTarget(new CorrectnessHarness($host, $port, $db, 'root', 'root'));

/** @var PhpFuzzer\Config $config */
$config->setAllowedExceptions([]);
$config->setMaxLen(256);
$config->setTarget(Closure::fromCallable($target));
