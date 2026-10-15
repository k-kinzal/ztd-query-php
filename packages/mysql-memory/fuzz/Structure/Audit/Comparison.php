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
 */
final class Comparison
{
    /**
     * @return array{sql: string, profile: string, mode: string, native: array<string, mixed>, memory: array<string, mixed>, matches: bool} Both complete observations and their strict equality
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
                $native->query("SET SESSION sql_mode = '" . $native->real_escape_string($mode) . "', collation_connection = 'utf8mb4_bin'");
                $session = (new Fixture())->session($endpoint, $native, $release, $definition->supervised());
                $session->text = 'unrelated invalid input';
                $session->variables->session['sql_mode'] = $mode;
                foreach (['character_set_client' => 'utf8mb4', 'character_set_connection' => 'utf8mb4', 'character_set_results' => 'utf8mb4', 'collation_connection' => 'utf8mb4_bin'] as $key => $value) {
                    $session->variables->session[$key] = $value;
                }
                $actual = (new Memory())->observe($session, $node, $this->parameters($node, $session));
                $expected = (new Native())->observe($native, $sql, count((new Bindings())->nulls($node)));

                $actual = (new CatalogRows())->comparable($node, $actual);
                $expected = (new CatalogRows())->comparable($node, $expected);

                return ['sql' => $sql, 'profile' => $profile->grammar->value, 'mode' => $mode, 'native' => $expected, 'memory' => $actual, 'matches' => $expected === $actual];
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }

    /**
     * Supplies NULL values in the types assigned before native statement execution.
     * Modern preparation defaults unbound markers to VARCHAR(16383); legacy
     * preparation exposes a zero-length binary VAR_STRING for NULL. This fixture never reads native output.
     *
     * @return list<array{null, \MySqlMemory\Typing\Domain}>
     */
    public function parameters(Statement|Scalar $node, \MySqlMemory\Session\Session $session): array
    {
        $values = (new Bindings())->nulls($node);
        if (str_starts_with($session->instance->version, '5.')) {
            $domain = new \MySqlMemory\Typing\Domain(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::String, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::VarString);

            return array_map(static fn (array $value): array => [null, $domain], $values);
        }
        $collation = $session->resolution()->connection;
        $domain = \MySqlMemory\Typing\Domain::string(16383, $collation)->withCollation($collation, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility::Coercible);

        return array_map(static fn (array $value): array => [null, $domain], $values);
    }
}
