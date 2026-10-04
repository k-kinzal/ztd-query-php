<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Query\SelectSlice;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;

#[CoversClass(SelectSlice::class)]
#[Medium]
final class SelectSliceTest extends TestCase
{
    public function testStatementLowersTheModernQuerySpecification(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('SELECT a, b FROM t WHERE a = 1');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertCount(2, $operation->statement->items);
        self::assertSame('t', $operation->statement->from?->name->name->value);
        self::assertNotNull($operation->statement->where);
        self::assertSame('SELECT a, b FROM t WHERE a = 1', $operation->toString());
    }

    public function testStatementReportsASetOperationAsAMissingRule(): void
    {
        $this->expectExceptionMessage('No semantic rule is implemented for: query_expression_body: query_expression_body UNION_SYM union_option query_expression_body');

        (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('SELECT 1 UNION SELECT 2');
    }

    public function testLegacyLowersTheSelectOfMySql57(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SELECT a FROM t WHERE a = 1');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame('SELECT a FROM t WHERE a = 1', $operation->toString());
        self::assertSame('SELECT 1', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SELECT 1')->toString());
    }

    public function testLegacyReportsAUnionAsAMissingRule(): void
    {
        $this->expectExceptionMessage('No semantic rule is implemented for: opt_union_clause: union_list');

        (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SELECT 1 UNION SELECT 2');
    }

    public function testOldestLowersTheSelectOfMySql56(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SELECT a FROM t WHERE a = 1');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame('SELECT a FROM t WHERE a = 1', $operation->toString());
        self::assertSame('SELECT 1', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SELECT 1')->toString());
    }

    public function testOldestReportsAnOrderingWithoutAFromClauseAsAMissingRule(): void
    {
        $this->expectExceptionMessage('No semantic rule is implemented for: opt_order_clause: order_clause');

        (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SELECT 1 ORDER BY 1');
    }

    public function testLegacyItemsReportsASelectOptionAsAMissingRule(): void
    {
        $this->expectExceptionMessage('No semantic rule is implemented for: select_options: select_option_list');

        (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SELECT DISTINCT a FROM t');
    }

    public function testItemsLowersEveryProjectedExpression(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SELECT a, b AS c, 1');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertCount(3, $operation->statement->items);
        self::assertInstanceOf(ColumnUse::class, $operation->statement->items[0]->expression);
        self::assertSame('c', $operation->statement->items[1]->alias?->value);
    }

    public function testItemsReportsAStarAsAMissingRule(): void
    {
        $this->expectExceptionMessage('No semantic rule is implemented for: select_item_list: *');

        (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('SELECT * FROM t');
    }

    public function testAliasLowersEverySpellingOfAnAlias(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-8.4.7');

        self::assertSame('SELECT a AS b FROM t', $semantics->analyze('SELECT a b FROM t')->toString());
        self::assertSame('SELECT a AS b FROM t', $semantics->analyze("SELECT a AS 'b' FROM t")->toString());
        self::assertSame('SELECT a AS b FROM t AS u', $semantics->analyze('SELECT a AS "b" FROM t u')->toString());
    }

    public function testFromLowersOneTableOrNothing(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-8.4.7');
        $operation = $semantics->analyze('SELECT a FROM db.t');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame('db', $operation->statement->from?->name->schema?->value);
        self::assertNull($semantics->analyze('SELECT 1')->inputRelation());
    }

    public function testFromReportsAJoinAsAMissingRule(): void
    {
        $this->expectExceptionMessage('No semantic rule is implemented for: table_reference: joined_table');

        (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('SELECT a FROM t JOIN u');
    }

    public function testTableReportsADerivedTableAsAMissingRule(): void
    {
        $this->expectExceptionMessage('No semantic rule is implemented for: table_factor: derived_table');

        (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('SELECT a FROM (SELECT 1) AS d');
    }

    public function testTableReportsAnIndexHintAsAMissingRule(): void
    {
        $this->expectExceptionMessage('No semantic rule is implemented for: opt_index_hints_list: index_hints_list');

        (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SELECT a FROM t USE INDEX (i)');
    }

    public function testWhereLowersAnAbsentClauseAsNull(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SELECT a FROM t');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertNull($operation->statement->where);
    }

    public function testDescendReportsAnUnexpectedStepAsAMissingRule(): void
    {
        $this->expectExceptionMessage('No semantic rule is implemented for: query_primary: explicit_table');

        (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('TABLE t');
    }

    public function testAbsentReportsAPresentClauseAsAMissingRule(): void
    {
        $this->expectExceptionMessage('No semantic rule is implemented for: opt_limit_clause: limit_clause');

        (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('SELECT 1 LIMIT 1');
    }
}
