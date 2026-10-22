<?php

/**
 * Compares grammar-generated statements (statement mode) on a MySQL server and on mysql-memory.
 *
 * Usage: MYSQL_VERSION=8.4.7 vendor/bin/php-fuzzer fuzz fuzz/fuzz_statement.php fuzz/corpus/statement/ --timeout=60
 * Any difference in rows, result columns, errors, warnings or table contents is a crash.
 * Server-wide catalog objects and global variables are restored before each observation.
 */

declare(strict_types=1);

use Faker\Factory;
use Fuzz\Target\Lifecycle;
use Fuzz\Target\Servers;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\MySql\MySqlProvider;

register_shutdown_function(static function (): void {
    if (function_exists('pcntl_alarm')) {
        pcntl_alarm(0);
    }
});

[$target, $grammar, $server] = (new Servers())->start(getenv('MYSQL_MEMORY_EMULATE') !== '0', true);
$provider = new MySqlProvider(Factory::create(), $grammar);
$planner = $provider->planner();
$constraints = GenerationPlan::fromRule(str_starts_with($grammar, 'mysql-5.') ? 'statement' : 'simple_statement_or_begin')->requiringNonEmpty();

/**
 * @var PhpFuzzer\Config $config
 */
$config->setAllowedExceptions([]);
$config->setMaxLen(80004);
$config->setTarget(static function (string $input) use ($provider, $planner, $constraints, $target): void {
    $sql = $provider->generate((new BytePlanCompiler())->compile($input, $planner, $constraints));
    $comparison = Lifecycle::handles($sql) ? (new Lifecycle())->compare($sql, $target->version) : $target->compare($sql);
    if ($comparison->difference !== null || $comparison->volatile) {
        throw new Error("mysql-memory differs from MySQL.\nInput (hex): " . bin2hex($input) . "\nSQL: {$sql}\n" . ($comparison->difference ?? $comparison->referenceDifference));
    }
});
