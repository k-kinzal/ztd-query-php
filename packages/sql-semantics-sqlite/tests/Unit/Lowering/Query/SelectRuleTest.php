<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Lowering\Query\SelectRule;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Query\Compound;
use SqlSemantics\Platform\Sqlite\Statement\Query\CompoundOperator;
use SqlSemantics\Platform\Sqlite\Statement\Query\CompoundStep;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\OutputOrdinal;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\SetQuantifier;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValuesClause;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\WithQuery;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;

#[CoversClass(SelectRule::class)]
#[Medium]
final class SelectRuleTest extends TestCase
{
    public function testSelectLowersAPlainQueryAndAQueryWithCommonTables(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $plain = $semantics->analyze('SELECT 1')->statement;
        $with = $semantics->analyze('WITH c AS (SELECT 1) SELECT * FROM c')->statement;
        $recursive = $semantics->analyze('WITH RECURSIVE c(n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM c WHERE n < 3) SELECT n FROM c')->statement;

        self::assertInstanceOf(Select::class, $plain);
        self::assertInstanceOf(WithQuery::class, $with);
        self::assertInstanceOf(WithQuery::class, $recursive);
        self::assertFalse($with->with->recursive);
        self::assertInstanceOf(Select::class, $with->body);
        self::assertTrue($recursive->with->recursive);
        self::assertInstanceOf(Compound::class, $recursive->with->tables[0]->query);
    }

    public function testBodyLowersACompoundQueryWithItsArmsInOrderAndTheTrailingOrderByAndLimit(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT 1 UNION SELECT 2 UNION ALL SELECT 3 EXCEPT SELECT 4 INTERSECT SELECT 5 ORDER BY 1 LIMIT 1');

        self::assertInstanceOf(Compound::class, $operation->statement);
        self::assertInstanceOf(Select::class, $operation->statement->first);
        self::assertSame([CompoundOperator::Union, CompoundOperator::UnionAll, CompoundOperator::Except, CompoundOperator::Intersect], array_map(static fn (CompoundStep $step): CompoundOperator => $step->operator, $operation->statement->steps));
        self::assertCount(1, $operation->statement->orderBy);
        self::assertInstanceOf(OutputOrdinal::class, $operation->statement->orderBy[0]->expression);
        self::assertNotNull($operation->statement->limit);
        $last = $operation->statement->steps[3]->query;
        self::assertInstanceOf(Select::class, $last);
        self::assertSame([], $last->orderBy);
        self::assertNull($last->limit);
        self::assertSame('SELECT 1 UNION SELECT 2 UNION ALL SELECT 3 EXCEPT SELECT 4 INTERSECT SELECT 5 ORDER BY 1 LIMIT 1', $operation->toString());
    }

    public function testBodyLeavesACompoundThatEndsInValuesWithoutOrderByAndLimit(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT 1 UNION VALUES (2)');

        self::assertInstanceOf(Compound::class, $operation->statement);
        self::assertInstanceOf(ValuesClause::class, $operation->statement->steps[0]->query);
        self::assertSame([], $operation->statement->orderBy);
        self::assertNull($operation->statement->limit);
        self::assertSame('SELECT 1 UNION VALUES (2)', $operation->toString());
    }

    public function testOperatorLowersEveryCompoundOperatorAsWritten(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('select 1 union select 2 union all select 3 except select 4 intersect select 5');

        self::assertInstanceOf(Compound::class, $operation->statement);
        self::assertSame(['UNION', 'UNION ALL', 'EXCEPT', 'INTERSECT'], array_map(static fn (CompoundStep $step): string => $step->operator->value, $operation->statement->steps));
        self::assertSame('SELECT 1 UNION SELECT 2 UNION ALL SELECT 3 EXCEPT SELECT 4 INTERSECT SELECT 5', $operation->toString());
    }

    public function testArmLowersASelectionOrAValuesClause(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $compound = $semantics->analyze('VALUES (1) UNION SELECT 2')->statement;
        $rows = $semantics->analyze('VALUES (1), (2)')->statement;

        self::assertInstanceOf(Compound::class, $compound);
        self::assertInstanceOf(ValuesClause::class, $compound->first);
        self::assertInstanceOf(Select::class, $compound->steps[0]->query);
        self::assertInstanceOf(ValuesClause::class, $rows);
        self::assertCount(2, $rows->rows);
    }

    public function testSelectionLowersEveryClauseInGrammarOrder(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT DISTINCT a FROM t WHERE b GROUP BY c HAVING d WINDOW w AS () ORDER BY e LIMIT 1 OFFSET 2');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame(SetQuantifier::Distinct, $operation->statement->quantifier);
        self::assertCount(1, $operation->statement->columns);
        self::assertInstanceOf(TableInput::class, $operation->statement->from);
        self::assertInstanceOf(ColumnUse::class, $operation->statement->where);
        self::assertCount(1, $operation->statement->groupBy);
        self::assertInstanceOf(ColumnUse::class, $operation->statement->having);
        self::assertCount(1, $operation->statement->windows);
        self::assertCount(1, $operation->statement->orderBy);
        self::assertInstanceOf(ColumnUse::class, $operation->statement->orderBy[0]->expression);
        self::assertSame('e', $operation->statement->orderBy[0]->expression->name->value);
        self::assertNotNull($operation->statement->limit);
        self::assertNotNull($operation->statement->limit->offset);
        self::assertSame('SELECT DISTINCT a FROM t WHERE b GROUP BY c HAVING d WINDOW w AS () ORDER BY e LIMIT 1 OFFSET 2', $operation->toString());
    }

    public function testGroupsTurnsAnIntegerConstantIntoAResultColumnPosition(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $grouped = $semantics->analyze('SELECT a, count(*) FROM t GROUP BY 1, a')->statement;
        $plain = $semantics->analyze('SELECT a FROM t')->statement;

        self::assertInstanceOf(Select::class, $grouped);
        self::assertInstanceOf(Select::class, $plain);
        self::assertCount(2, $grouped->groupBy);
        self::assertInstanceOf(OutputOrdinal::class, $grouped->groupBy[0]);
        self::assertInstanceOf(IntegerLiteral::class, $grouped->groupBy[0]->constant);
        self::assertInstanceOf(ColumnUse::class, $grouped->groupBy[1]);
        self::assertSame([], $plain->groupBy);
    }

    public function testHavingIsNullWithoutTheClause(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $bare = $semantics->analyze('SELECT a FROM t GROUP BY a')->statement;
        $filtered = $semantics->analyze('SELECT a FROM t GROUP BY a HAVING count(*) > 1')->statement;

        self::assertInstanceOf(Select::class, $bare);
        self::assertInstanceOf(Select::class, $filtered);
        self::assertNull($bare->having);
        self::assertNotNull($filtered->having);
        self::assertSame('SELECT a FROM t GROUP BY a HAVING count(*) > 1', $semantics->analyze('SELECT a FROM t GROUP BY a HAVING count(*) > 1')->toString());
    }

    public function testLimitKeepsTheSpellingOfTheOffset(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $plain = $semantics->analyze('SELECT 1 LIMIT 5');
        $offset = $semantics->analyze('SELECT 1 LIMIT 5 OFFSET 2');
        $comma = $semantics->analyze('SELECT 1 LIMIT 2, 5');
        $none = $semantics->analyze('SELECT 1');

        self::assertInstanceOf(Select::class, $plain->statement);
        self::assertInstanceOf(Select::class, $offset->statement);
        self::assertInstanceOf(Select::class, $comma->statement);
        self::assertInstanceOf(Select::class, $none->statement);
        self::assertNull($none->statement->limit);
        self::assertNotNull($plain->statement->limit);
        self::assertNull($plain->statement->limit->offset);
        self::assertFalse($plain->statement->limit->comma);
        self::assertNotNull($offset->statement->limit);
        self::assertFalse($offset->statement->limit->comma);
        self::assertInstanceOf(IntegerLiteral::class, $offset->statement->limit->offset);
        self::assertSame('2', $offset->statement->limit->offset->digits);
        self::assertNotNull($comma->statement->limit);
        self::assertTrue($comma->statement->limit->comma);
        self::assertInstanceOf(IntegerLiteral::class, $comma->statement->limit->count);
        self::assertSame('5', $comma->statement->limit->count->digits);
        self::assertInstanceOf(IntegerLiteral::class, $comma->statement->limit->offset);
        self::assertSame('2', $comma->statement->limit->offset->digits);
        self::assertSame('SELECT 1 LIMIT 5 OFFSET 2', $offset->toString());
        self::assertSame('SELECT 1 LIMIT 2, 5', $comma->toString());
    }
}
