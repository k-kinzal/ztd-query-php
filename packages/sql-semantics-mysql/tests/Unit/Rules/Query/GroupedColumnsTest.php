<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\GroupedColumns;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\GroupingRule;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\NonGroupedColumn;

#[CoversClass(GroupedColumns::class)]
#[Medium]
final class GroupedColumnsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, GroupingRule|null, string}>
     */
    public static function providerCheckReportsTheFirstUndeterminedColumn(): iterable
    {
        yield 'aggregated without GROUP BY' => ['SELECT a, COUNT(*) FROM t1', GroupingRule::WithoutGroupBy, 'fz.t1.a'];
        yield 'not grouped' => ['SELECT b, COUNT(*) FROM t1 GROUP BY a', GroupingRule::NotDetermined, 'fz.t1.b'];
        yield 'primary key' => ['SELECT id, b FROM t1 GROUP BY id', null, ''];
        yield 'joined key' => ['SELECT t2.name, t1.b FROM t1 JOIN t2 ON t1.id = t2.id GROUP BY t2.id', null, ''];
        yield 'order key' => ['SELECT a FROM t1 GROUP BY a ORDER BY b', GroupingRule::NotDetermined, 'fz.t1.b'];
        yield 'constant' => ["SELECT a, b FROM t1 WHERE b = 'x' GROUP BY a", null, ''];
        yield 'expression' => ['SELECT a + 1 FROM t1 GROUP BY a + 1', null, ''];
        yield 'unique not null' => ['SELECT name, flag FROM t2 GROUP BY name', null, ''];
        yield 'alias' => ['SELECT x.b FROM t1 x GROUP BY x.a', GroupingRule::NotDetermined, 'fz.x.b'];
        yield 'aggregated order' => ['SELECT COUNT(*) FROM t1 ORDER BY a', null, ''];
        yield 'derived' => ['SELECT d.p FROM (SELECT a AS p, b FROM t1) d GROUP BY d.b', GroupingRule::NotDetermined, 'd.p'];
        yield 'distinct' => ['SELECT DISTINCT a FROM t1 ORDER BY b', GroupingRule::NotSelected, 'fz.t1.b'];
        yield 'star' => ['SELECT *, COUNT(*) FROM t1', GroupingRule::WithoutGroupBy, 'fz.t1.id'];
    }

    #[DataProvider('providerCheckReportsTheFirstUndeterminedColumn')]
    public function testCheckReportsTheFirstUndeterminedColumn(string $sql, ?GroupingRule $rule, string $column): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $tables = [
            $semantics->analyze('CREATE TABLE fz.t1 (id INT PRIMARY KEY, a INT, b VARCHAR(20))'),
            $semantics->analyze("CREATE TABLE fz.t2 (id INT PRIMARY KEY, name VARCHAR(20) NOT NULL, flag TINYINT, UNIQUE KEY uk (name))"),
        ];
        $diagnostics = $semantics->analyze($sql, $semantics->context($tables, true, new SearchPath('fz')))->facts->diagnostics;

        self::assertSame($rule === null ? 0 : 1, count($diagnostics));
        if ($rule !== null) {
            self::assertInstanceOf(NonGroupedColumn::class, $diagnostics[0]);
            self::assertSame([$rule, $column], [$diagnostics[0]->rule, $diagnostics[0]->column]);
        }
    }

    public function testCheckLeavesAStatementWithAProblemAlone(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');

        self::assertCount(1, $semantics->analyze('SELECT a AS x, b AS x FROM t GROUP BY x', [$table])->facts->diagnostics);
    }

    public function testSameComparesNamesWithoutRegardToCase(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $left = $semantics->analyze('SELECT A FROM t')->statement;
        $right = $semantics->analyze('SELECT a FROM t')->statement;

        self::assertTrue((new GroupedColumns())->same($left, $right));
        self::assertFalse((new GroupedColumns())->same($left, $semantics->analyze('SELECT b FROM t')->statement));
    }
}
