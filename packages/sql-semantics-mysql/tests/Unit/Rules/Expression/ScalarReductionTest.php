<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Expression\ScalarReduction;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery;

#[CoversClass(ScalarReduction::class)]
#[Medium]
final class ScalarReductionTest extends TestCase
{
    public function testRetainedFilterDistinguishesLegacyPresenceFromModernTruth(): void
    {
        $rule = new ScalarReduction();
        $truth = new \SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral(true);
        self::assertTrue($rule->retainedFilter($truth, \SqlSemantics\Contract\GrammarRelease::MySql5744));
        self::assertFalse($rule->retainedFilter($truth, \SqlSemantics\Contract\GrammarRelease::MySql847));
        self::assertFalse($rule->retainedFilter(null, \SqlSemantics\Contract\GrammarRelease::MySql5651));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerQueries(): iterable
    {
        yield 'literal' => ['SELECT 1', true];
        yield 'null' => ['SELECT NULL', true];
        yield 'zero limit' => ['SELECT 1 LIMIT 0', true];
        yield 'offset' => ['SELECT 1 LIMIT 1 OFFSET 1', true];
        yield 'dual' => ['SELECT 1 FROM DUAL', true];
        yield 'ordered' => ['SELECT 1 ORDER BY 1', true];
        yield 'grouped' => ['SELECT 1 GROUP BY 1', true];
        yield 'rollup' => ['SELECT 1 GROUP BY 1 WITH ROLLUP', false];
        yield 'distinct' => ['SELECT DISTINCT 1', true];
        yield 'with' => ['WITH c AS (SELECT 2) SELECT 1', true];
        yield 'nested' => ['SELECT (SELECT COUNT(*))', true];
        yield 'table' => ['SELECT a FROM t', false];
        yield 'where' => ['SELECT 1 WHERE FALSE', false];
        yield 'true filter' => ['SELECT 1 WHERE TRUE', true];
        yield 'true comparison' => ['SELECT 1 WHERE 1=1', true];
        yield 'false comparison' => ['SELECT 1 WHERE 1=2', false];
        yield 'having' => ['SELECT 1 HAVING TRUE', false];
        yield 'aggregate' => ['SELECT COUNT(*)', false];
        yield 'window' => ['SELECT ROW_NUMBER() OVER ()', false];
        yield 'set' => ['SELECT 1 UNION SELECT 2', false];
    }

    #[DataProvider('providerQueries')]
    public function testExpressionKeepsTheBoundOccurrenceWhenItsWrapperCanBeEliminated(string $sql, bool $reduced): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT (' . $sql . ')');
        $subquery = $operation->field(0)->expression;
        self::assertInstanceOf(ScalarSubquery::class, $subquery);
        $expression = (new ScalarReduction())->expression($subquery->query);

        self::assertSame($reduced, $expression !== null);
        self::assertSame($expression, $subquery->scalarInformation()->replacement);
    }

    public function testExpressionRetainsMultipleColumnsAsARow(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT (1,2)=(SELECT 1,2)');
        $comparison = $operation->field(0)->expression;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Comparison::class, $comparison);
        $subquery = $comparison->right;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Subquery\RowSubquery::class, $subquery);
        self::assertNull((new ScalarReduction())->expression($subquery->query));
        self::assertNull($subquery->scalarInformation()->replacement);
    }

    public function testPredicateRetainsTheMySql56EqualityReductionAcrossWhere(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT (SELECT 7 FROM DUAL WHERE FALSE)');
        $subquery = $operation->field(0)->expression;
        self::assertInstanceOf(ScalarSubquery::class, $subquery);
        self::assertNotNull((new ScalarReduction())->predicate($subquery->query, \SqlSemantics\Contract\GrammarRelease::MySql5651));
        self::assertNull((new ScalarReduction())->predicate($subquery->query, \SqlSemantics\Contract\GrammarRelease::MySql5744));
    }

    public function testFilteredKeepsFalseAndUnknownConditions(): void
    {
        $rules = new ScalarReduction();

        self::assertFalse($rules->filtered(null));
        self::assertFalse($rules->filtered(new \SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral(true)));
        self::assertTrue($rules->filtered(new \SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral(false)));
        self::assertTrue($rules->filtered(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse(new \SqlSemantics\Statement\Identifier\Name('a'))));
    }

    public function testExpressionRetainsADirectColumnSubqueryInMySql56(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.6.51');
        $table = $semantics->analyze('CREATE TABLE t(id INT PRIMARY KEY)');
        $operation = $semantics->analyze('SELECT (SELECT id LIMIT 0) FROM t', [$table]);
        $subquery = $operation->field(0)->expression;
        self::assertInstanceOf(ScalarSubquery::class, $subquery);

        self::assertNull((new ScalarReduction())->expression($subquery->query, \SqlSemantics\Contract\GrammarRelease::MySql5651));
        self::assertNull($subquery->scalarInformation()->replacement);
        self::assertSame(\SqlSemantics\Statement\Type\Nullability::NotNull, $operation->field(0)->nullability);
    }

    /**
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function providerNullability(): iterable
    {
        yield 'literal' => ['SELECT 1', false, true];
        yield 'aggregate' => ['SELECT COUNT(*)', false, true];
        yield 'window' => ['SELECT ROW_NUMBER() OVER ()', false, true];
        yield 'table' => ['SELECT 1 FROM t', false, false];
        yield 'having' => ['SELECT 1 HAVING TRUE', false, false];
        yield 'true where' => ['SELECT 1 WHERE TRUE', false, true];
        yield 'false where' => ['SELECT 1 WHERE FALSE', false, false];
        yield 'true aggregate where' => ['SELECT COUNT(*) WHERE 1', false, true];
        yield 'false aggregate where' => ['SELECT COUNT(*) WHERE 0', false, false];
        yield 'modern limit' => ['SELECT COUNT(*) LIMIT 0', false, false];
        yield 'legacy limit attribute' => ['SELECT COUNT(*) LIMIT 0', true, true];
        yield 'wrapped limit' => ['(SELECT COUNT(*)) LIMIT 0', false, false];
    }

    #[DataProvider('providerNullability')]
    public function testPreservesNullabilityDescribesTheScalarResultAttribute(string $sql, bool $legacy, bool $preserved): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT (' . $sql . ')');
        $subquery = $operation->field(0)->expression;
        self::assertInstanceOf(ScalarSubquery::class, $subquery);

        self::assertSame($preserved, (new ScalarReduction())->preservesNullability($subquery->query, $legacy ? \SqlSemantics\Contract\GrammarRelease::MySql5651 : \SqlSemantics\Contract\GrammarRelease::MySql847));
    }

    public function testExpressionReducesATablelessRollupInMySql91(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-9.1.0'))->analyze('SELECT (SELECT 1 GROUP BY 1 WITH ROLLUP)');
        $subquery = $operation->field(0)->expression;
        self::assertInstanceOf(ScalarSubquery::class, $subquery);

        self::assertNotNull($subquery->scalarInformation()->replacement);
        self::assertSame(\SqlSemantics\Statement\Type\Nullability::Nullable, $operation->field(0)->nullability);
    }
    public function testExpressionRetainsATrueFilterOnMySql57(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SELECT (SELECT 1 WHERE 1)');
        $subquery = $operation->field(0)->expression;
        self::assertInstanceOf(ScalarSubquery::class, $subquery);

        self::assertNull($subquery->scalarInformation()->replacement);
        self::assertSame(\SqlSemantics\Statement\Type\Nullability::Nullable, $operation->field(0)->nullability);
    }

}
