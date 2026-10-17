<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Grouping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\Grouping\GroupKeys;
use SqlSemantics\Platform\MySql\Statement\Query\Select;

#[CoversClass(GroupKeys::class)]
#[Small]
final class GroupKeysTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function providerGroups(): iterable
    {
        yield 'implicit aggregate' => ['SELECT MIN(n) FROM t', true, false];
        yield 'ungrouped rows' => ['SELECT n FROM t', false, false];
        yield 'arithmetic' => ['SELECT MIN(n) FROM t GROUP BY 1+1', true, false];
        yield 'call' => ["SELECT MIN(n) FROM t GROUP BY CONCAT('a','b')", true, false];
        yield 'variable' => ['SELECT MIN(n) FROM t GROUP BY @x', true, false];
        yield 'assigned variable' => ['SELECT @x:=MIN(n) FROM t GROUP BY @x', false, true];
        yield 'alias' => ['SELECT 1+1 AS k,MIN(n) FROM t GROUP BY k', true, false];
        yield 'position' => ['SELECT 1+1 AS k,MIN(n) FROM t GROUP BY 1', true, false];
        yield 'constant rollup' => ['SELECT MIN(n) FROM t GROUP BY 1+1 WITH ROLLUP', false, false];
        yield 'column rollup' => ['SELECT MIN(n) FROM t GROUP BY n WITH ROLLUP', false, true];
        yield 'random' => ['SELECT MIN(n) FROM t GROUP BY RAND(2)', false, true];
        yield 'retained subquery' => ['SELECT MIN(n) FROM t GROUP BY (SELECT 1 FROM t LIMIT 1)', false, true];
    }

    #[DataProvider('providerGroups')]
    public function testDirectAndVaryingResolveTheGroupingBoundary(string $sql, bool $direct, bool $varying): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $query = $semantics->analyze($sql, [$semantics->analyze('CREATE TABLE t(n INT)')]);
        $select = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $select);
        $select = $select->query;
        self::assertInstanceOf(Select::class, $select);
        $keys = new GroupKeys();

        self::assertSame($direct, $keys->direct($select, $query->facts));
        self::assertSame($varying, $keys->varying($select, $query->facts));
    }

    public function testConstantReadsReducedExpressionsAndRejectsChangingCalls(): void
    {
        $query = (new Semantics(Dialect::MySql))->analyze('SELECT (SELECT 1),RAND(2),@x');
        $keys = new GroupKeys();

        self::assertInstanceOf(\SqlSemantics\Statement\Scalar::class, $query->field(0)->expression);
        self::assertInstanceOf(\SqlSemantics\Statement\Scalar::class, $query->field(1)->expression);
        self::assertInstanceOf(\SqlSemantics\Statement\Scalar::class, $query->field(2)->expression);
        self::assertTrue($keys->constant($query->field(0)->expression, $query->facts));
        self::assertFalse($keys->constant($query->field(1)->expression, $query->facts));
        self::assertFalse($keys->constant($query->field(2)->expression, $query->facts, ['x']));
    }

    public function testAssignedCollectsWritesAcrossNestedQueries(): void
    {
        $query = (new Semantics(Dialect::MySql))->analyze('SELECT @X:=1,(SELECT @Y:=2 LIMIT 1)');

        self::assertSame(['x', 'y'], (new GroupKeys())->assigned($query->statement));
    }

    public function testChildrenPreservesTheOrderOfSyntaxChildren(): void
    {
        $query = (new Semantics(Dialect::MySql))->analyze('SELECT 1+2');
        $expression = $query->field(0)->expression;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic::class, $expression);

        self::assertSame([$expression->left, $expression->right], (new GroupKeys())->children($expression));
    }
    public function testVaryingRetainsColumnDependenceAmongConstantKeys(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $query = $semantics->analyze('SELECT MIN(n) FROM t GROUP BY 1+1,n', [$semantics->analyze('CREATE TABLE t(n INT)')]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $query->statement);
        self::assertInstanceOf(Select::class, $query->statement->query);

        self::assertTrue((new GroupKeys())->varying($query->statement->query, $query->facts));
        self::assertTrue((new GroupKeys())->varying($query->statement->query));
    }

}
