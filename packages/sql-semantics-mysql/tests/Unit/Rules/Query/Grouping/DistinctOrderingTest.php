<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Grouping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\Grouping\DistinctOrdering;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\AggregateInOrdering;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\NonGroupedColumn;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Statement\Scalar;

#[CoversClass(DistinctOrdering::class)]
#[Medium]
final class DistinctOrderingTest extends TestCase
{
    /**
     * @return iterable<string, array{string, class-string|null}>
     */
    public static function providerCheck(): iterable
    {
        yield 'unselected aggregate' => ['SELECT DISTINCT ABS(n) FROM t GROUP BY n ORDER BY SUM(n)', AggregateInOrdering::class];
        yield 'selected aggregate parent' => ['SELECT DISTINCT SUM(n) FROM t GROUP BY n ORDER BY SUM(n)+1', AggregateInOrdering::class];
        yield 'first aggregate' => ['SELECT DISTINCT ABS(n) FROM t GROUP BY n ORDER BY SUM(n)+n', AggregateInOrdering::class];
        yield 'first column' => ['SELECT DISTINCT ABS(n) FROM t GROUP BY n ORDER BY n+SUM(n)', NonGroupedColumn::class];
        yield 'rollup aggregate' => ['SELECT DISTINCT n FROM t GROUP BY n WITH ROLLUP ORDER BY SUM(n)', AggregateInOrdering::class];
        yield 'outer aggregate' => ['SELECT DISTINCT ABS(n) FROM t GROUP BY n ORDER BY (SELECT SUM(n))', AggregateInOrdering::class];
        yield 'selected expression' => ['SELECT DISTINCT SUM(n) FROM t GROUP BY n ORDER BY SUM(n)', null];
        yield 'redundant distinct' => ['SELECT DISTINCT n FROM t GROUP BY n ORDER BY SUM(n)', null];
        yield 'alias parent' => ['SELECT DISTINCT SUM(n) AS x FROM t GROUP BY n ORDER BY ABS(x)', null];
        yield 'single group' => ['SELECT DISTINCT SUM(n) FROM t ORDER BY COUNT(*)', null];
        yield 'independent aggregate' => ['SELECT DISTINCT ABS(n) FROM t GROUP BY n ORDER BY (SELECT SUM(n) FROM t)', null];
    }

    /**
     * @param class-string|null $expected
     */
    #[DataProvider('providerCheck')]
    public function testCheckUsesAggregateOwnershipAndWrittenErrorOrder(string $sql, ?string $expected): void
    {
        $semantics = new Semantics(Dialect::MySql, '8.4.7');
        $operation = $semantics->analyze($sql, [$semantics->analyze('CREATE TABLE t(n INT)')]);
        $diagnostics = $operation->facts->diagnostics;

        self::assertSame($expected, isset($diagnostics[0]) ? $diagnostics[0]::class : null);
    }

    public function testRedundantRequiresEveryGroupExpressionAndNoRollup(): void
    {
        $semantics = new Semantics(Dialect::MySql, '8.4.7');
        $table = $semantics->analyze('CREATE TABLE t(n INT)');
        $plain = $semantics->analyze('SELECT DISTINCT n FROM t GROUP BY n', [$table]);
        $rollup = $semantics->analyze('SELECT DISTINCT n FROM t GROUP BY n WITH ROLLUP', [$table]);
        self::assertInstanceOf(Select::class, $plain->statement);
        self::assertInstanceOf(Select::class, $rollup->statement);
        self::assertInstanceOf(SelectExpression::class, $plain->statement->items[0]);
        self::assertInstanceOf(SelectExpression::class, $rollup->statement->items[0]);

        self::assertTrue((new DistinctOrdering())->redundant($plain->statement, [$plain->statement->items[0]->expression], $plain->facts));
        self::assertFalse((new DistinctOrdering())->redundant($rollup->statement, [$rollup->statement->items[0]->expression], $rollup->facts));
    }

    public function testMissingSkipsAliasReferencesButFindsWrittenAggregateCalls(): void
    {
        $semantics = new Semantics(Dialect::MySql, '8.4.7');
        $operation = $semantics->analyze('SELECT DISTINCT SUM(n) AS x FROM t GROUP BY n ORDER BY x+1, SUM(n)+1', [$semantics->analyze('CREATE TABLE t(n INT)')]);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $owned = array_fill_keys(array_map(spl_object_id(...), $operation->facts->query($select)->aggregates), true);
        $checker = new DistinctOrdering();

        self::assertNull($checker->missing($select->orderBy[0]->expression, [], $operation->facts, [], $owned));
        self::assertInstanceOf(Scalar::class, $checker->missing($select->orderBy[1]->expression, [], $operation->facts, [], $owned));
    }

    /**
     * @return iterable<string, array{string, string, class-string|null}>
     */
    public static function providerLegacyOrdering(): iterable
    {
        yield '5.6 column' => ['5.6.51', 'n', null];
        yield '5.6 aggregate' => ['5.6.51', 'SUM(n)', null];
        yield '5.7 column' => ['5.7.44', 'n', NonGroupedColumn::class];
        yield '5.7 aggregate' => ['5.7.44', 'SUM(n)', AggregateInOrdering::class];
    }

    /**
     * @param class-string|null $expected
     */
    #[DataProvider('providerLegacyOrdering')]
    public function testCheckPreservesTheReleaseWhereDistinctOrderingWasIntroduced(string $version, string $ordering, ?string $expected): void
    {
        $semantics = new Semantics(Dialect::MySql, $version);
        $operation = $semantics->analyze('SELECT DISTINCT ABS(n) FROM t GROUP BY n ORDER BY ' . $ordering, [$semantics->analyze('CREATE TABLE t(n INT)')]);
        $diagnostics = $operation->facts->diagnostics;

        self::assertSame($expected, isset($diagnostics[0]) ? $diagnostics[0]::class : null);
    }
}
