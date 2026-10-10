<?php

/**
 * Compares grammar-generated statements (write mode) on a MySQL server and on mysql-memory.
 *
 * Usage: MYSQL_VERSION=8.4.7 vendor/bin/php-fuzzer fuzz fuzz/fuzz_write.php fuzz/corpus/write/ --timeout=60
 * Any difference in rows, result columns, errors, warnings or table contents is a crash.
 */

declare(strict_types=1);

use Faker\Factory;
use Fuzz\Target\Plans;
use Fuzz\Target\Servers;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\MySql\MySqlProvider;

register_shutdown_function(static function (): void {
    if (function_exists('pcntl_alarm')) {
        pcntl_alarm(0);
    }
});

[$target, $grammar, $server] = (new Servers())->start(getenv('MYSQL_MEMORY_EMULATE') !== '0');
$provider = new MySqlProvider(Factory::create(), $grammar);
$planner = $provider->planner();
$plans = new Plans();
$constraints = $plans->plan('write', $grammar);

/**
 * @var PhpFuzzer\Config $config
 */
$config->setAllowedExceptions([]);
$config->setMaxLen(4096);
$config->setTarget(static function (string $input) use ($provider, $planner, $constraints, $plans, $target): void {
    $sql = $plans->statement('write', $provider->generate((new BytePlanCompiler())->compile($input, $planner, $constraints)));
    $target->verify($sql, $input);
});
