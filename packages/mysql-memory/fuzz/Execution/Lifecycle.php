<?php

declare(strict_types=1);

namespace Fuzz\Execution;

use Container\Endpoint;
use Fuzz\Container\DisposableMySql;
use Fuzz\Fixture\Servers;
use Fuzz\Fixture\Tables;
use Fuzz\Observation\Comparison;
use Fuzz\Observation\Observer;
use Fuzz\Observation\Record;
use MySqlMemory\Server\MemoryDatabase;
use PDO;
use PDOException;
use RuntimeException;
use Testcontainers\Testcontainers;

/**
 * Compares lifecycle commands against two independently initialized native servers.
 *
 * Each observation owns a disposable server. Primary outcomes, transitions and
 * post-statement rows/definitions remain raw evidence even if a later probe fails.
 * Container readiness, connection termination and restart have distinct phases;
 * the surrounding PHP-Fuzzer input timeout still bounds the entire comparison.
 */
final class Lifecycle
{
    /**
     * @var list<Record> Completed and partial observations of the current input
     */
    public array $observations = [];

    /**
     * Runs the same input on two native fixtures before comparing the memory fixture.
     * Independent native initialization proceeds concurrently through Testcontainers'
     * ordinary SQL readiness checks, without changing the per-input alarm.
     *
     * @throws RuntimeException When startup, a transition or an observation fails
     */
    public function compare(string $sql, LifecycleAction $action, string $version, string $mode = '', bool $emulate = true): Comparison
    {
        $first = new Record('native', $sql, $mode);
        $repeated = new Record('native', $sql, $mode);
        $memory = new Record('memory', $sql, $mode);
        $first->phase = $repeated->phase = 'container startup';
        $memory->phase = 'pending';
        $this->observations = [$first, $repeated, $memory];
        $companion = new DisposableMySql($version);
        $companionPort = filter_var(getenv('MYSQL_MEMORY_LIFECYCLE_REPEATED_PORT'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        $companion->withPortStrategy(is_int($companionPort)
            ? new \Testcontainers\Containers\PortStrategy\StaticPortStrategy($companionPort)
            : new \Testcontainers\Containers\PortStrategy\RandomPortStrategy());
        $definition = new DisposableMySql($version, $companion);
        $port = filter_var(getenv('MYSQL_MEMORY_LIFECYCLE_NATIVE_PORT'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        if (is_int($port)) {
            $definition->withPortStrategy(new \Testcontainers\Containers\PortStrategy\StaticPortStrategy($port));
        }
        try {
            $container = Testcontainers::run($definition);
            $second = $definition->companionInstance ?? throw new RuntimeException('The repeated native lifecycle fixture did not start.');
            $first->phase = $repeated->phase = 'fixture ready';
            return $this->compareReady($container->getData(Endpoint::class), $second->getData(Endpoint::class), $definition->supervised(), $action, $version, $emulate, $first, $repeated, $memory);
        } finally {
            try {
                if ($definition->startedInstance !== null) {
                    $definition->dispose();
                    $first->cleanup = 'disposed';
                }
            } finally {
                if ($companion->startedInstance !== null) {
                    $companion->dispose();
                    $repeated->cleanup = 'disposed';
                }
            }
        }
    }

    /**
     * Captures startup inputs before executing SQL, and requires a stable native reference.
     *
     * @throws RuntimeException When a ready fixture becomes unavailable or an observation fails
     */
    public function compareReady(Endpoint $firstEndpoint, Endpoint $secondEndpoint, bool $supervised, LifecycleAction $action, string $version, bool $emulate, Record $first, Record $repeated, Record $memory): Comparison
    {
        $first->phase = 'fixture capabilities';
        $native = $this->connection($firstEndpoint, $emulate, $version);
        $fixtures = new Servers();
        $globals = $fixtures->globals($native, $version, $first);
        $priorities = $fixtures->resourcePriorities($native, $version, $first);
        $processors = $fixtures->resourceProcessors($native, $version, $first);
        $zones = $fixtures->timeZones($native, $first);
        $expected = $this->observe($native, $firstEndpoint->dsn(), $firstEndpoint->username, $firstEndpoint->password, $action, $first);
        $native = null;
        $again = $this->observe($this->connection($secondEndpoint, $emulate, $version), $secondEndpoint->dsn(), $secondEndpoint->username, $secondEndpoint->password, $action, $repeated);
        $observations = ['native' => $first->raw, 'repeated' => $repeated->raw];
        if ($expected !== $again) {
            return new Comparison(true, referenceDifference: 'lifecycle reference: ' . var_export([$expected, $again], true), observations: $observations);
        }
        $memory->phase = 'server startup';
        $server = MemoryDatabase::start($version, [], $globals, supervised: $supervised, resourcePriorities: $priorities, resourceProcessors: $processors, timeZones: $zones);
        try {
            $owner = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => $emulate]);
            $actual = $this->observe($owner, $server->dsn(), 'root', '', $action, $memory);
            $observations['memory'] = $memory->raw;
            return new Comparison(false, $expected === $actual ? null : 'lifecycle: ' . var_export([$expected, $actual], true), observations: $observations);
        } finally {
            try {
                $server->assertNoInternalFailure();
            } finally {
                $server->stop();
                $memory->cleanup = 'disposed';
            }
        }
    }

    /**
     * Opens the observed connection after the repository's container readiness check.
     *
     * @throws RuntimeException When the fixture cannot serve SQL or advertises another release
     */
    public function connection(Endpoint $endpoint, bool $emulate, string $version): PDO
    {
        $connection = (new LifecycleTransition())->connect($endpoint->dsn(), $endpoint->username, $endpoint->password, false) ?? throw new RuntimeException('The native lifecycle fixture is not SQL-ready.');
        (new Servers())->assertVersion($connection, $version);
        $connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, $emulate);
        return $connection;
    }

    /**
     * Executes one lifecycle statement and retains its outcome before observing the transition.
     * Legitimate SQL refusals retain conditions and do not pretend to shut down.
     *
     * @return array<string, mixed>
     * @throws RuntimeException When setup, conditions, transition or state cannot be observed
     */
    public function observe(PDO $owner, string $dsn, string $user, string $password, LifecycleAction $action, Record $record): array
    {
        $record->phase = 'fixture setup';
        (new Tables())->execute($owner, 'CREATE DATABASE fz', $record);
        (new Tables())->execute($owner, 'USE fz', $record);
        foreach ((new Tables())->statements() as $statement) {
            (new Tables())->execute($owner, $statement, $record);
        }
        if ($record->mode !== '') {
            (new Tables())->execute($owner, "SET SESSION sql_mode = CONCAT_WS(',', @@SESSION.sql_mode, " . $owner->quote($record->mode) . ')', $record);
        }
        $record->phase = 'statement';
        $this->primary($owner, $record);
        if (isset($record->raw['error'])) {
            $record->phase = 'conditions';
            $record->raw['warnings'] = (new Observer())->warnings($owner);
            $after = $owner;
        } else {
            $after = (new LifecycleTransition())->await($owner, $dsn, $user, $password, $action, $record);
        }
        if ($after !== null) {
            $record->phase = 'post-statement tables';
            $record->raw['tables'] = (new Observer())->tables($after, 'fz', $record);
            $record->phase = 'post-statement definitions';
            $record->raw['definitions'] = (new Observer())->definitions($after, 'fz', $record);
            $record->phase = 'post-statement status';
            $record->raw['status'] = (new Observer())->probe($after, "SHOW GLOBAL STATUS LIKE 'Aborted_clients'", $record);
            $results = $record->raw['status']['results'];
            $rows = is_array($results) && is_array($results[0] ?? null) ? ($results[0]['rows'] ?? null) : null;
            if (!is_array($rows) || count($rows) !== 1 || !is_array($rows[0]) || count($rows[0]) !== 2
                || ($rows[0][0] ?? null) !== 'Aborted_clients' || !is_string($rows[0][1] ?? null) || !ctype_digit($rows[0][1])) {
                throw new RuntimeException('Cannot read the lifecycle Aborted_clients counter.');
            }
        }
        $record->phase = 'complete';
        return $record->raw;
    }

    /**
     * Records the primary completion or full SQL error in either PDO error mode.
     *
     * @throws RuntimeException When result metadata cannot be read
     */
    public function primary(PDO $owner, Record $record): void
    {
        try {
            $statement = $owner->query($record->sql);
            if ($statement === false) {
                $error = $owner->errorInfo();
                $record->raw['error'] = [$error[1] ?? null, $error[0], $error[2] ?? null];
            } else {
                $record->raw['result'] = (new Observer())->result($owner, $statement, true);
                $statement->closeCursor();
            }
        } catch (PDOException $failure) {
            $record->raw['error'] = [$failure->errorInfo[1] ?? null, $failure->errorInfo[0] ?? null, $failure->errorInfo[2] ?? $failure->getMessage()];
        }
    }
}
