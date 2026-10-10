<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Grouping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\Grouping\OrderingAggregates;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\AggregateInOrdering;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\NonGroupedColumn;
use SqlSemantics\Platform\MySql\Statement\Query\Select;

#[CoversClass(OrderingAggregates::class)]
#[Medium]
final class OrderingAggregatesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<AggregateInOrdering>}>
     */
    public static function providerCheck(): iterable
    {
        yield 'local' => ['SELECT id FROM t ORDER BY SUM(a)', [new AggregateInOrdering(1)]];
        yield 'correlated' => ['SELECT id FROM t ORDER BY (SELECT SUM(t.a))', [new AggregateInOrdering(1)]];
        yield 'second key' => ['SELECT 1 FROM t ORDER BY id, SUM(a)', [new AggregateInOrdering(2)]];
        yield 'select aggregates' => ['SELECT COUNT(*) FROM t ORDER BY SUM(a)', []];
        yield 'select owns nested aggregate' => ['SELECT (SELECT COUNT(t.a)) FROM t ORDER BY SUM(a)', []];
        yield 'having aggregates' => ['SELECT 1 FROM t HAVING COUNT(*)>0 ORDER BY SUM(a)', []];
        yield 'grouped' => ['SELECT id FROM t GROUP BY id ORDER BY SUM(a)', []];
        yield 'inner owns aggregate' => ['SELECT id FROM t ORDER BY (SELECT SUM(1))', []];
    }

    /**
     * @param list<AggregateInOrdering> $expected
     */
    #[DataProvider('providerCheck')]
    public function testCheckUsesResolvedOwnership(string $sql, array $expected): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t(id INT PRIMARY KEY, a INT)');

        self::assertEquals($expected, $semantics->analyze($sql, [$table])->facts->diagnostics);
    }

    public function testCheckKeepsTheLegacyGroupingDiagnostic(): void
    {
        $semantics = new Semantics(Dialect::MySql, '5.6.51');
        $table = $semantics->analyze('CREATE TABLE t(a INT)');
        $diagnostics = $semantics->analyze('SELECT a FROM t ORDER BY SUM(a)', [$table])->facts->diagnostics;

        self::assertCount(1, $diagnostics);
        self::assertInstanceOf(NonGroupedColumn::class, $diagnostics[0]);
    }

    public function testOccurrencesFindsOwnedAggregatesInsideCorrelatedQueries(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t(a INT)');
        $operation = $semantics->analyze('SELECT 1 FROM t ORDER BY (SELECT SUM(t.a)), (SELECT SUM(1))', [$table]);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $owned = array_fill_keys(array_map(spl_object_id(...), $operation->facts->query($select)->aggregates), true);
        $rule = new OrderingAggregates();

        self::assertCount(1, $owned);
        self::assertSame($owned, $rule->occurrences($select->orderBy[0]->expression, $owned));
        self::assertSame([], $rule->occurrences($select->orderBy[1]->expression, $owned));
    }
}
