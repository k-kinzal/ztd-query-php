<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use mysqli;
use mysqli_result;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Statement\Identifier\Name;
use Testcontainers\Containers\PortStrategy\StaticPortStrategy;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class DerivedOptionDomainTest extends TestCase
{
    /**
     * @return iterable<string, array{string, DisposableMySql, list<Statement>}>
     */
    public static function providerOptions(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $definition = new DisposableMySql($release);
            $port = filter_var(getenv('MYSQL_MEMORY_DERIVED_PORT'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
            if (is_int($port)) {
                $definition->withPortStrategy(new StaticPortStrategy($port));
            }
            $nodes = [];
            foreach ([[], [\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::All], [\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::Distinct], [\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::BigResult], [\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::SmallResult], [\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::StraightJoin]] as $options) {
                $table = new Select($options, [new \SqlSemantics\Platform\MySql\Statement\Query\Star()], new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('t'), new Name('d'))));
                $nodes[] = new QueryRoot(new Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\Star()], new \SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable($table, new Name('derived_values'))));
            }
            yield $release => [$release, $definition, $nodes];
        }
    }

    /**
     * @param list<Statement> $nodes The same derived query roles before and after populated table creation
     */
    #[DataProvider('providerOptions')]
    public function testSameDerivedQueryRolesExecuteAcrossPopulatedStates(string $release, DisposableMySql $definition, array $nodes): void
    {
        $container = Testcontainers::run($definition);
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $session = (new Instance($release))->connect();
                $fixture = ['SET NAMES utf8mb4 COLLATE utf8mb4_general_ci'];
                array_map(static fn (string $sql): bool|mysqli_result => $native->query($sql), $fixture);
                array_map($session->run(...), $fixture);
                $stages = [[], ['CREATE DATABASE d', 'CREATE TABLE d.t(v INT)', 'INSERT INTO d.t VALUES(2),(4),(2)'], ['UPDATE d.t SET v=v+10']];
                $results = array_map(static function (array $setup) use ($native, $session, $nodes): array {
                    array_map(static fn (string $sql): bool|mysqli_result => $native->query($sql), $setup);
                    array_map($session->run(...), $setup);
                    $profile = $session->semantics()->profile();
                    $expected = array_map(static fn (Statement $node): string => serialize((new Native())->observe($native, (new Reference())->sql($node, $profile))), $nodes);
                    $session->text = 'unrelated invalid input';
                    $actual = array_map(static fn (Statement $node): string => serialize((new Memory())->observe($session, $node)), $nodes);

                    return [$expected, $actual];
                }, $stages);

                self::assertSame(array_column($results, 0), array_column($results, 1));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
