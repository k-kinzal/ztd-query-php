<?php

declare(strict_types=1);

namespace Fuzz\Structure\Audit;

use Container\Endpoint;
use Fuzz\Structure\Bindings;
use Fuzz\Structure\Profiles;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use mysqli;
use RuntimeException;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Statement\Scalar;
use Testcontainers\Containers\PortStrategy\StaticPortStrategy;
use Testcontainers\Testcontainers;

/**
 * Compares the complete outcome of the original structure with its native rendering.
 * A fresh server isolates even statements that partly change state before failing.
 * Both sessions receive the same explicit timestamp, so statement clocks compare
 * SQL-controlled state independently of container startup and observation delays.
 */
final class Comparison
{
    /**
     * @return array{sql: string, profile: string, mode: string, native: array<string, mixed>, memory: array<string, mixed>, matches: bool, contracts: list<string>, transitions: list<array{sql: string, native: array<string, mixed>, memory: array<string, mixed>, matches: bool, contracts: list<string>}>} Original observations and any database-selection history, without replacing the original structure
     */
    public function compare(Statement|Scalar $node, LanguageProfile $profile, ?string $mode = null, ?int $port = null): array
    {
        $release = substr($profile->grammar->value, 6);
        $mode ??= (new Profiles())->mode($profile);
        $definition = new DisposableMySql($release);
        if ($port !== null) {
            $definition->withPortStrategy(new StaticPortStrategy($port));
        }
        $container = Testcontainers::run($definition);
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->set_charset('utf8mb4');
                $native->query("SET SESSION sql_mode = '" . $native->real_escape_string($mode) . "', collation_connection = 'utf8mb4_bin', timestamp = 1700000000");
                $session = (new Fixture())->session($endpoint, $native, $release, $definition->supervised());
                $session->text = 'unrelated invalid input';
                (new SessionSetup())->apply($session, $mode);
                $observation = $this->observe($node, $profile, $native, $session, $endpoint);
                $database = $observation['matches'] ? $this->database($observation['native']) : null;
                $history = $database === null ? [] : $this->selected($node, $profile, $native, $session, $endpoint, $database);

                return [...$observation, 'profile' => $profile->grammar->value, 'mode' => $mode, 'matches' => $observation['matches'] && !in_array(false, array_column($history, 'matches'), true), 'transitions' => $history];
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }

    /**
     * Selects an explicit schema fixture for a matching database-state refusal.
     * The reported name supplies setup state only; it never changes the tested
     * structure or substitutes a native response for a memory observation.
     *
     * @param array<string, mixed> $observation The retained native outcome
     * @throws RuntimeException When an unknown-database error has an unrecognized diagnostic shape
     */
    public function database(array $observation): ?\SqlSemantics\Statement\Identifier\Name
    {
        $error = $observation['error'] ?? null;
        if (!is_array($error)) {
            return null;
        }
        if (($error[0] ?? null) === 1046) {
            return new \SqlSemantics\Statement\Identifier\Name('structure_fixture');
        }
        if (($error[0] ?? null) !== 1049) {
            return null;
        }
        $message = $error[2] ?? null;
        if (!is_string($message) || preg_match("/\\AUnknown database '(.*)'\\z/s", $message, $matches) !== 1) {
            throw new RuntimeException('The native unknown-database observation has no schema fixture name.');
        }
        return new \SqlSemantics\Statement\Identifier\Name($matches[1]);
    }

    /**
     * Executes the supplied object directly and compares its complete native rendering.
     *
     * @return array{sql: string, native: array<string, mixed>, memory: array<string, mixed>, matches: bool, contracts: list<string>}
     */
    public function observe(Statement|Scalar $node, LanguageProfile $profile, mysqli $native, \MySqlMemory\Session\Session $session, Endpoint $endpoint): array
    {
        $sql = (new Reference())->sql($node, $profile, '?');
        $status = Status\Listing::capture($node, $native, $session, $sql);
        $password = Password\Contract::capture($node, $endpoint, $session);
        $process = Process\Listing::capture($node, $native, $session, $sql);
        $actual = (new Memory())->observe($session, $node, $this->parameters($node, $session));
        $expected = (new Native())->observe($native, $sql, count((new Bindings())->nulls($node)));

        return ['sql' => $sql, 'native' => $expected, 'memory' => $actual, ...(new Observations())->compare($node, $session->instance->version, $expected, $actual, $status, password: $password, process: $process)];
    }

    /**
     * Resolves an initial missing database, then repeats the exact original object.
     * Every setup observation remains part of the comparison. A later SQL error is
     * retained for classification; selecting a database does not certify the structure.
     *
     * @return list<array{sql: string, native: array<string, mixed>, memory: array<string, mixed>, matches: bool, contracts: list<string>}>
     * @throws RuntimeException When a matching fixture error prevents the state transition
     */
    public function selected(Statement|Scalar $node, LanguageProfile $profile, mysqli $native, \MySqlMemory\Session\Session $session, Endpoint $endpoint, ?\SqlSemantics\Statement\Identifier\Name $database = null): array
    {
        $database ??= new \SqlSemantics\Statement\Identifier\Name('structure_fixture');
        $steps = [new \SqlSemantics\Platform\MySql\Statement\Server\Database\CreateDatabase(true, $database), new \SqlSemantics\Platform\MySql\Statement\Utility\Explain\UseDatabase($database), $node];
        $history = [];
        foreach ($steps as $index => $step) {
            $observation = $this->observe($step, $profile, $native, $session, $endpoint);
            $history[] = $observation;
            if (!$observation['matches']) {
                break;
            }
            if ($index < 2 && isset($observation['native']['error'])) {
                throw new RuntimeException('The database selection fixture could not be established: ' . serialize($history));
            }
        }
        return $history;
    }

    /**
     * Supplies NULL values in the types assigned before native statement execution.
     * Modern preparation derives context types from SQL Semantics and defaults
     * other markers to VARCHAR(16383); legacy
     * preparation exposes a zero-length binary VAR_STRING for NULL. This fixture never reads native output.
     *
     * @return list<array{null, \MySqlMemory\Typing\Domain}>
     */
    public function parameters(Statement|Scalar $node, \MySqlMemory\Session\Session $session): array
    {
        $values = (new Bindings())->nulls($node);
        if ($values === []) {
            return [];
        }
        if (str_starts_with($session->instance->version, '5.')) {
            $domain = new \MySqlMemory\Typing\Domain(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Null, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::VarString, coercibility: \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility::Coercible);

            return array_map(static fn (array $value): array => [null, $domain], $values);
        }
        $collation = $session->resolution()->connection;
        $domain = \MySqlMemory\Typing\Domain::string(16383, $collation)->withCollation($collation, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility::Coercible);

        $statement = $node instanceof Statement ? $node : new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression($node)]));
        $operation = (new \Fuzz\Structure\Execution())->operation($session, $statement, array_fill(0, count($values), [null, $domain]));
        $types = \SqlSemantics\Platform\MySql\Statement\Variable\ParameterInference::infer($operation);

        return array_values(array_map(static fn (array $entry): array => [null, \MySqlMemory\Typing\Domain::of($entry[0], true)], $types));
    }
}
