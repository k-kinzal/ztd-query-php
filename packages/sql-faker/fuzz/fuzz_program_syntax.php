<?php

declare(strict_types=1);

namespace SqlFaker\Fuzz;

use Faker\Factory;
use LogicException;
use PDO;
use SqlFaker\Coverage\CoverageException;
use SqlFaker\Coverage\GrammarCoverage;
use SqlFaker\Coverage\Verification\FeatureFeedback;
use SqlFaker\Coverage\Verification\SqlContext;
use SqlFaker\Fuzz\Target\InfrastructureFailure;
use SqlFaker\Fuzz\Target\MySqlSyntaxCheck;
use SqlFaker\Fuzz\Target\ObservedCheck;
use SqlFaker\Fuzz\Target\OracleEnvironment;
use SqlFaker\Fuzz\Target\PgRawCheck;
use SqlFaker\Fuzz\Target\PgSyntaxCheck;
use SqlFaker\Fuzz\Target\SqliteProgramCheck;
use SqlFaker\Fuzz\Target\VerificationRegistry;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Generation\Plan\ProductionPattern;
use SqlFaker\MySqlProvider;
use SqlFaker\PostgreSqlProvider;
use SqlFaker\SqliteProvider;

/**
 * Supplementary native target for original program roots and scanner selector modes.
 * Uses disposable externally provisioned, pinned engines; see fuzz/README.md.
 * The first byte selects a declared context; all remaining bytes compile one immutable plan.
 */
$dialect = getenv('SQLFAKER_DIALECT');
$directory = OracleEnvironment::setting('SQLFAKER_COVERAGE_DIR', __DIR__ . '/coverage/program-' . $dialect);
$coverage = new GrammarCoverage($directory);
$cases = [];
if ($dialect === 'mysql') {
    $pdo = new PDO(OracleEnvironment::setting('SQLFAKER_MYSQL_DSN', 'mysql:host=127.0.0.1;port=3306;charset=utf8mb4'), OracleEnvironment::setting('SQLFAKER_MYSQL_USER', 'root'), OracleEnvironment::setting('SQLFAKER_MYSQL_PASSWORD', 'root'));
    $provider = new MySqlProvider(Factory::create(), 'mysql-8.4.7', $coverage);
    $environment = OracleEnvironment::mysql($pdo, 'mysql-8.4.7');
    $check = new MySqlSyntaxCheck($pdo, 'mysql-8.4.7', true);
    foreach ([['', ''], ['SELECT ', ''], ['CREATE TABLE t(x INT) ', ''], ['SELECT ', ''], ['SELECT * FROM ', ' AS t'], ['SELECT ', '']] as $ordinal => [$prefix, $suffix]) {
        $cases[] = ['root' => 'start_entry', 'ordinal' => $ordinal, 'mode' => 'mysql-selector-' . $ordinal, 'context' => new SqlContext($prefix, $suffix), 'check' => $check];
    }
} elseif ($dialect === 'pg') {
    $connection = pg_connect(OracleEnvironment::setting('SQLFAKER_PG_CONNECTION', 'host=127.0.0.1 port=5432 dbname=fuzz_test user=test password=test'));
    if ($connection === false) {
        throw new InfrastructureFailure('Cannot connect to the pinned PostgreSQL program oracle.');
    }
    $provider = new PostgreSqlProvider(Factory::create(), 'pg-17.2', $coverage);
    $environment = OracleEnvironment::pg($connection);
    foreach (range(0, 5) as $mode) {
        $cases[] = ['root' => 'parse_toplevel', 'ordinal' => $mode, 'mode' => 'raw-parser-' . $mode, 'context' => new SqlContext(), 'check' => new PgRawCheck($connection, $mode, OracleEnvironment::setting('SQLFAKER_PG_MODULE', '/tmp/sqlfaker_raw_parse.so'))];
    }
    $check = new PgSyntaxCheck($connection);
    $cases[] = ['root' => 'json_behavior_type', 'ordinal' => null, 'mode' => 'json-behavior', 'context' => new SqlContext(), 'check' => $check];
    $cases[] = ['root' => 'bare_label_keyword', 'ordinal' => null, 'mode' => 'bare-label', 'context' => new SqlContext('SELECT 1 '), 'check' => $check];
} elseif ($dialect === 'sqlite') {
    $provider = new SqliteProvider(Factory::create(), 'sqlite-3.47.2', $coverage);
    OracleEnvironment::sqlite(new PDO('sqlite::memory:'));
    $library = getenv('SQLFAKER_SQLITE_LIBRARY');
    if ($library === false || $library === '') {
        throw new InfrastructureFailure('SQLFAKER_SQLITE_LIBRARY must select the SQLite 3.47.2 shared library.');
    }
    $check = new SqliteProgramCheck($library);
    $environment = $check->configuration();
    $cases[] = ['root' => 'input', 'ordinal' => null, 'mode' => 'sqlite-program', 'context' => new SqlContext(), 'check' => $check];
} else {
    throw new InfrastructureFailure('SQLFAKER_DIALECT must be mysql, pg or sqlite.');
}
$planner = $provider->planner();
$feedback = new FeatureFeedback();
$features = array_flip(array_keys($coverage->inventory()->entries));
$revision = OracleEnvironment::revision();
$recorders = new VerificationRegistry($coverage, $revision, $directory . '/verification');
$generations = 0;
$stop = null;
register_shutdown_function(static function () use ($coverage, $recorders): void {
    if (function_exists('pcntl_alarm')) {
        pcntl_alarm(0);
    }
    $coverage->flush();
    $recorders->flush();
});
if (function_exists('pcntl_signal')) {
    pcntl_async_signals(true);
    foreach ([SIGINT, SIGTERM] as $signal) {
        pcntl_signal($signal, static function (int $received) use (&$stop): void {
            $stop ??= $received;
        });
    }
}

/**
 * @var \PhpFuzzer\Config $config
 */
$config->setAllowedExceptions([]);
$config->setMaxLen(60005);
$config->setTarget(static function (string $input) use ($provider, $planner, $cases, $environment, $coverage, $features, $feedback, $recorders, &$generations, &$stop): void {
    if ($stop !== null) {
        exit(128 + $stop);
    }
    try {
        $case = $cases[ord($input[0] ?? "\0") % count($cases)];
        $root = $case['root'];
        $constraints = $case['ordinal'] === null ? GenerationPlan::fromRule($root) : GenerationPlan::constrained($root, [$root => [ProductionPattern::at($case['ordinal'])]]);
        $plan = (new \SqlFaker\Generation\Choice\BytePlanCompiler())->compile(substr($input, 1), $planner, $constraints->withExpansionBudget(100));
        $fragment = $provider->generate($plan);
        if ($provider->generate($plan) !== $fragment) {
            throw new LogicException('A frozen plan generated different SQL.');
        }
        $context = $case['context'];
        if ($root === 'json_behavior_type') {
            $function = preg_match('/\A(?:TRUE|FALSE|UNKNOWN)\z/i', trim($fragment)) === 1 ? 'JSON_EXISTS' : 'JSON_QUERY';
            $context = new SqlContext("SELECT $function('{}', '$' ", ' ON ERROR)');
        }
        $configuration = [...$environment, 'checkMode' => $case['mode'], 'prefix' => $context->prefix, 'suffix' => $context->suffix];
        $recorder = $recorders->forConfiguration($configuration);
        $sql = $context->sql($fragment);
        $trace = $coverage->lastGeneration();
        foreach ($trace['reachedIds'] ?? [] as $id) {
            \PhpFuzzer\FuzzingContext::$edges[-1 - $features[$id]] = 1;
        }
        if ($trace !== null) {
            \PhpFuzzer\FuzzingContext::$edges += $feedback->edges($trace);
        }
        $verdict = (new ObservedCheck($case['check'], $recorder))->verify($sql, $input);
        if ($trace !== null) {
            \PhpFuzzer\FuzzingContext::$edges += $feedback->edges($trace, $verdict->status);
        }
        if (++$generations % 100 === 0) {
            $coverage->flush();
            $recorder->flush();
        }
    } catch (InfrastructureFailure|CoverageException $failure) {
        fwrite(STDERR, $failure->getMessage() . "\n");
        exit(2);
    }
});
