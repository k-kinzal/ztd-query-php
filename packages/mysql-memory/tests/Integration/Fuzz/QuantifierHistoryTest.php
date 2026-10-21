<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use mysqli;
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
final class QuantifierHistoryTest extends TestCase
{
    /**
     * @return iterable<string, array{list<M\QueryRoot>}> Expression query roles accepted by MySQL 5.6
     */
    public static function providerQueries(): iterable
    {
        $queries = [];
        foreach ([[M\Query\SelectOption::All, M\Query\SelectOption::Distinct], [M\Query\SelectOption::Distinct, M\Query\SelectOption::All]] as $options) {
            foreach (['scalar', 'exists', 'in', 'any'] as $kind) {
                $query = new M\Query\Select($options, [new M\Query\SelectExpression(new M\Name\ColumnUse(new Name('v')))], new M\Relation\TableReference(new QualifiedName(new Name('t'), new Name('d'))));
                $expression = match ($kind) {
                    'scalar' => new M\Expression\Subquery\ScalarSubquery($query),
                    'exists' => new M\Expression\Subquery\Exists($query),
                    'in' => new M\Expression\Subquery\InQuery(new M\Literal\NumberLiteral('2'), $query),
                    'any' => new M\Expression\Subquery\QuantifiedComparison(new M\Literal\NumberLiteral('2'), M\Expression\ComparisonOperator::Equal, M\Expression\Subquery\Quantifier::Any, $query),
                };
                $queries[] = new M\QueryRoot(new M\Query\Select([], [new M\Query\SelectExpression($expression, new Name('v'))]));
            }
        }
        yield 'legacy expression roles' => [$queries];
    }

    /**
     * @param list<M\QueryRoot> $queries Preserved structures across missing, empty, duplicate and multi-valued table states
     */
    #[DataProvider('providerQueries')]
    public function testTheSameExpressionRolesObserveDataAndCardinalityWithoutInputSql(array $queries): void
    {
        $container = Testcontainers::run(new DisposableMySql('5.6.51'));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $session = (new Instance('5.6.51'))->connect();
            $native->set_charset('utf8mb4');
            $session->query('SET NAMES utf8mb4');
            $stages = [[], ['CREATE DATABASE d', 'CREATE TABLE d.t(v INT)'], ['INSERT INTO d.t VALUES(2),(2)'], ['INSERT INTO d.t VALUES(3)'], ['DELETE FROM d.t'], ['DROP TABLE d.t']];
            $observations = array_map(static function (array $setup) use ($native, $session, $queries): array {
                array_map(static fn (string $sql): bool => $native->query($sql) === true, $setup);
                array_map($session->query(...), $setup);
                return array_map(static function (M\QueryRoot $query) use ($native, $session): array {
                    $sql = (new Reference())->sql($query, $session->semantics()->profile());
                    $expected = (new Native())->observe($native, $sql);
                    $session->text = 'opaque non-SQL request label';
                    $actual = (new Memory())->observe($session, $query);
                    self::assertSame(serialize($expected), serialize($actual), $sql);
                    return $actual;
                }, $queries);
            }, $stages);
            self::assertSame([[null]], $observations[1][0]['results'][0]['rows']);
            self::assertSame([['2']], $observations[2][0]['results'][0]['rows']);
            self::assertSame(1242, $observations[3][0]['error'][0] ?? null);
            self::assertSame([[null]], $observations[4][0]['results'][0]['rows']);
            self::assertSame('opaque non-SQL request label', $session->text);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}
