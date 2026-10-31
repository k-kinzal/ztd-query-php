<?php

/**
 * SQL Faker -> the same SQL on Testcontainers MySQL and MemoryDB -> equal replies.
 *
 * MYSQL_VERSION selects the release. SQLFAKER_SQL_MODE and SQLFAKER_DIAGNOSTICS
 * select the SQL Faker grammar seed profile. MYSQL_MEMORY_FUZZ_SECONDS bounds a
 * seedless campaign; PHP_FUZZER_SEED reproduces PHP-Fuzzer's mutations.
 *
 * Usage: MYSQL_VERSION=8.4.7 vendor/bin/php-fuzzer fuzz fuzz/fuzz_mysql_execution.php /path/to/sql-faker/seeds --timeout=60
 */

declare(strict_types=1);

use Container\Endpoint;
use Container\MySqlRelease;
use Faker\Factory;
use Fuzz\Target\ExecutionTarget;
use MySqlMemory\Server\MemoryDatabase;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Generation\SqlGenerator;
use SqlFaker\MySql\Generation\GenerationContext;
use SqlFaker\MySql\Grammar\MySqlGrammar;
use SqlFaker\MySql\MySqlProvider;
use Testcontainers\Containers\ReuseMode;
use Testcontainers\Testcontainers;

$version = getenv('MYSQL_VERSION');
$version = $version === false || $version === '' ? MySqlRelease::DEFAULT : $version;
$mode = getenv('SQLFAKER_SQL_MODE');
$mode = $mode === false ? '' : $mode;
if (!in_array($mode, ['', 'HIGH_NOT_PRECEDENCE', 'PIPES_AS_CONCAT'], true)) {
    throw new InvalidArgumentException('Unsupported SQL Faker grammar seed profile: ' . $mode);
}
$grammarVersion = 'mysql-' . $version;
$faker = Factory::create();
if (getenv('SQLFAKER_DIAGNOSTICS') === '1') {
    $grammar = MySqlGrammar::load($grammarVersion)->identified();
    $context = new GenerationContext($grammar, $faker, $grammarVersion, $mode === 'HIGH_NOT_PRECEDENCE', $mode === 'PIPES_AS_CONCAT');
    $provider = new SqlGenerator($grammar, $faker, $context->lexicalGrammar, startSymbol: $context->startSymbol);
} else {
    $provider = new MySqlProvider($faker, $grammarVersion, highNotPrecedence: $mode === 'HIGH_NOT_PRECEDENCE', pipesAsConcat: $mode === 'PIPES_AS_CONCAT');
}
$planner = $provider->planner();
$constraints = GenerationPlan::fromRule(str_starts_with($version, '5.') ? 'statement' : 'simple_statement_or_begin')->requiringNonEmpty();
$seconds = getenv('MYSQL_MEMORY_FUZZ_SECONDS');
$duration = $seconds === false ? null : filter_var($seconds, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($duration === false) {
    throw new InvalidArgumentException('MYSQL_MEMORY_FUZZ_SECONDS must be a positive integer.');
}
$configuredSeed = getenv('PHP_FUZZER_SEED');
$seed = $configuredSeed === false ? random_int(0, 2147483647) : filter_var($configuredSeed, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 2147483647]]);
if ($seed === false) {
    throw new InvalidArgumentException('PHP_FUZZER_SEED must be an integer from 0 to 2147483647.');
}
mt_srand($seed);

/**
 * Starts one pair of ordinary database connections for a generated SQL history.
 * Successful RESTART or SHUTDOWN ends that history after its reply is compared.
 * The next input starts with a new pair; no post-shutdown scheduling race is
 * treated as a SQL result, and no generated statement is discarded.
 *
 * @return array{ExecutionTarget, Closure(): void}
 */
$start = static function () use ($version, $mode): array {
    $containerClass = MySqlRelease::container($version);
    $reference = Testcontainers::run((new $containerClass())->withReuseMode(ReuseMode::ADD()));
    $server = null;
    $connected = false;
    try {
        $endpoint = $reference->getData(Endpoint::class);
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, 'test', $endpoint->port);
        $identityResult = $native->query('SELECT USER()');
        if (!$identityResult instanceof mysqli_result) {
            throw new RuntimeException('MySQL must return its client identity.');
        }
        $identity = $identityResult->fetch_column();
        $identityResult->free();
        $prefix = $endpoint->username . '@';
        if (!is_string($identity) || !str_starts_with($identity, $prefix) || strlen($identity) === strlen($prefix)) {
            throw new RuntimeException('MySQL must identify the configured user and its connecting host.');
        }
        /** Docker and the local server see different peers; configure the same client identity. */
        $server = MemoryDatabase::start($version, ['test'], clientHost: substr($identity, strlen($prefix)));
        $memory = new mysqli($server->host, 'root', '', 'test', $server->port);
        $identityResult = $memory->query('SELECT USER()');
        if (!$identityResult instanceof mysqli_result || $identityResult->fetch_column() !== $identity) {
            throw new RuntimeException('Both connections must expose the configured client identity.');
        }
        $identityResult->free();
        foreach ([$native, $memory] as $connection) {
            $connection->set_charset('utf8mb4');
            $connection->query("SET SESSION sql_mode = '" . $connection->real_escape_string($mode) . "'");
            $connection->query("SET SESSION time_zone = '+00:00'");
            $connection->query('SET SESSION timestamp = 1700000000');
            $connection->query('GRANT PROXY ON CURRENT_USER TO CURRENT_USER WITH GRANT OPTION');
            if (!str_starts_with($version, '5.')) {
                /**
                 * Start each generated history with empty logs, independent of container provisioning.
                 */
                $connection->query(in_array($version, ['8.0.44', '8.1.0', '8.2.0', '8.3.0'], true) ? 'RESET MASTER' : 'RESET BINARY LOGS AND GTIDS');
            }
            $connection->query('FLUSH TABLES');
        }
        mysqli_report(MYSQLI_REPORT_OFF);
        $target = new ExecutionTarget($native, $memory, $server);
        $connected = true;
        return [$target, static function () use ($native, $memory, $server, $reference): void {
            static $stopped = false;
            if ($stopped) {
                return;
            }
            $stopped = true;
            $native->close();
            $memory->close();
            $server->stop();
            $reference->stop();
        }];
    } finally {
        if (!$connected) {
            $server?->stop();
            $reference->stop();
        }
    }
};
[$target, $stop] = $start();
register_shutdown_function(static function () use (&$stop): void {
    $stop();
});
register_shutdown_function(static function () use (&$target): void {
    if (function_exists('pcntl_alarm')) {
        pcntl_alarm(0);
    }
    if ($target->attempts !== $target->verified || $target->verified === 0) {
        exit(1);
    }
    echo "Verified {$target->verified} inputs.\n";
});
echo 'Execution profile: ' . json_encode(['grammar' => $grammarVersion, 'mode' => $mode, 'diagnostics' => getenv('SQLFAKER_DIAGNOSTICS') === '1', 'seed' => $seed], JSON_THROW_ON_ERROR) . "\n";

/** @var PhpFuzzer\Config $config */
$config->setAllowedExceptions([]);
$config->setMaxLen(80004);
$config->setTarget(static function (string $input) use ($provider, $planner, $constraints, &$target, &$stop, $start, $duration): void {
    if ($target->attempts !== $target->verified) {
        fwrite(STDERR, "Stopping after the preceding execution counterexample.\n");
        exit(1);
    }
    /** @var int|float|null $started */
    static $started = null;
    $started ??= hrtime(true);
    $target->attempts++;
    static $terminal = false;
    if ($terminal) {
        $attempts = $target->attempts;
        $verified = $target->verified;
        $stop();
        [$target, $stop] = $start();
        $target->attempts = $attempts;
        $target->verified = $verified;
        $terminal = false;
    }
    $sql = $provider->generate((new BytePlanCompiler())->compile($input, $planner, $constraints));
    $succeeded = $target->verify($sql, $input);
    $terminal = $succeeded && preg_match('/\A\s*(?:RESTART|SHUTDOWN)\s*;?\s*\z/i', $sql) === 1;
    $elapsed = (hrtime(true) - $started) / 1_000_000_000;
    if ($duration !== null && $elapsed >= $duration) {
        echo "Completed {$target->verified} matching inputs in {$elapsed} seconds.\n";
        exit(0);
    }
});
