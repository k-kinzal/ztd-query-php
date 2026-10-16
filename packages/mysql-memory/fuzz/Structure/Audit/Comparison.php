<?php

declare(strict_types=1);

namespace Fuzz\Structure\Audit;

use Container\Endpoint;
use Fuzz\Structure\Bindings;
use Fuzz\Structure\Profiles;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use mysqli;
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
     * @return array{sql: string, profile: string, mode: string, native: array<string, mixed>, memory: array<string, mixed>, matches: bool, contracts: list<string>} Both original observations, equality and explicit comparison contracts
     */
    public function compare(Statement|Scalar $node, LanguageProfile $profile, ?string $mode = null, ?int $port = null): array
    {
        $release = substr($profile->grammar->value, 6);
        $mode ??= (new Profiles())->mode($profile);
        $sql = (new Reference())->sql($node, $profile, '?');
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
                $actual = (new Memory())->observe($session, $node, $this->parameters($node, $session));
                $expected = (new Native())->observe($native, $sql, count((new Bindings())->nulls($node)));

                return ['sql' => $sql, 'profile' => $profile->grammar->value, 'mode' => $mode, 'native' => $expected, 'memory' => $actual, ...(new Observations())->compare($node, $release, $expected, $actual)];
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
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
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-' . $session->instance->version, \SqlSemantics\Platform\MySql\Mode::fromString((string) $session->variables->read('sql_mode')), (new \Fuzz\Structure\Execution())->parameters($node));
        $context = $semantics->context($session->instance->dictionary->declarations(), true, $session->variables->database === '' ? null : new \SqlSemantics\Contract\SearchPath($session->variables->database), $session->resolution(array_fill(0, count($values), $domain->resolved())));
        $types = \SqlSemantics\Platform\MySql\Statement\Variable\ParameterInference::infer(new \SqlSemantics\Statement\Operation($context, $statement));

        return array_values(array_map(static fn (array $entry): array => [null, \MySqlMemory\Typing\Domain::of($entry[0], true)], $types));
    }
}
