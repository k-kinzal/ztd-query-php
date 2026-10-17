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
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class LegacySetStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<M\QueryRoot>}>
     */
    public static function providerStructures(): iterable
    {
        $operands = [static fn (): M\Literal\NumberLiteral => new M\Literal\NumberLiteral('1'), static fn (): M\Literal\NumberLiteral => new M\Literal\NumberLiteral('18446744073709551615'), static fn (): M\Literal\NumberLiteral => new M\Literal\NumberLiteral('1.25'), static fn (): M\Literal\NullLiteral => new M\Literal\NullLiteral(), static fn (): M\Name\ColumnUse => new M\Name\ColumnUse(new Name('f')), static fn (): M\Name\ColumnUse => new M\Name\ColumnUse(new Name('c')), static fn (): M\Name\ColumnUse => new M\Name\ColumnUse(new Name('s')), static fn (): M\Name\ColumnUse => new M\Name\ColumnUse(new Name('u'))];
        $nodes = [];
        foreach ($operands as $left) {
            foreach ($operands as $right) {
                foreach ([M\Query\Set\SetQuantifier::All, M\Query\Set\SetQuantifier::Distinct] as $quantifier) {
                    $first = new M\Query\Select([], [new M\Query\SelectExpression($left(), new Name('value'))], new M\Relation\TableReference(new QualifiedName(new Name('t'))));
                    $last = new M\Query\Select([], [new M\Query\SelectExpression($right())], new M\Relation\TableReference(new QualifiedName(new Name('t'))));
                    $nodes[] = new M\QueryRoot(new M\Query\Set\SetOperation($first, M\Query\Set\SetOperator::Union, $quantifier, $last));
                }
            }
        }
        foreach (['5.6.51', '5.7.44'] as $release) {
            yield $release => [$release, $nodes];
        }
    }

    /**
     * @param list<M\QueryRoot> $nodes Directly composed scalar and set values
     */
    #[DataProvider('providerStructures')]
    public function testTheSameSetStructuresResolveAcrossAbsentPopulatedAndRemovedTables(string $release, array $nodes): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $session = (new Instance($release))->connect();
            $native->query('CREATE DATABASE d');
            $native->query('USE d');
            $session->query('CREATE DATABASE d; USE d');
            $observed = array_map(static function (array $history) use ($native, $session, $nodes): array {
                array_map(static fn (string $setup): mysqli_result|bool => $native->query($setup), $history);
                array_map($session->query(...), $history);
                return array_map(static function (M\QueryRoot $node) use ($native, $session): array {
                    $expected = (new Native())->observe($native, (new Reference())->sql($node, $session->semantics()->profile()));
                    $session->text = 'unrelated invalid input';
                    $actual = (new Memory())->observe($session, $node);
                    return [serialize($expected), serialize($actual)];
                }, $nodes);
            }, [[], ['CREATE TABLE t(f FLOAT,c MEDIUMINT,s VARCHAR(8),u BIGINT UNSIGNED)', "INSERT INTO t VALUES(1.5,3,'abc',18446744073709551615),(2.5,4,'def',2)"], ['DROP TABLE t']]);
            $observed = array_merge(...$observed);

            self::assertSame(array_column($observed, 0), array_column($observed, 1));
            self::assertSame('unrelated invalid input', $session->text);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}
