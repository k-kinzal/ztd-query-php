<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Lowering\Expression\PredicateRule;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Between;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\NullTest;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\NullTestForm;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\PatternMatch;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\PatternOperator;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery\InList;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery\InQuery;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery\InTable;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;

#[CoversClass(PredicateRule::class)]
#[Medium]
final class PredicateRuleTest extends TestCase
{
    public function testExpressionLowersTheNullTestsByTheirSpelling(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT a ISNULL, a NOTNULL, a NOT NULL FROM t');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame([NullTestForm::IsNull, NullTestForm::NotNull, NullTestForm::NotNullWords], array_map(static function (object $column): NullTestForm {
            self::assertInstanceOf(ResultColumn::class, $column);
            self::assertInstanceOf(NullTest::class, $column->expression);
            self::assertInstanceOf(ColumnUse::class, $column->expression->operand);

            return $column->expression->form;
        }, $operation->statement->columns));
        self::assertSame('SELECT a ISNULL, a NOTNULL, a NOT NULL FROM t', $operation->toString());
    }

    public function testExpressionLowersRangeTests(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT a BETWEEN 1 AND 2, a NOT BETWEEN 1 AND 2 FROM t');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(ResultColumn::class, $operation->statement->columns[0]);
        self::assertInstanceOf(ResultColumn::class, $operation->statement->columns[1]);
        $plain = $operation->statement->columns[0]->expression;
        $negated = $operation->statement->columns[1]->expression;
        self::assertInstanceOf(Between::class, $plain);
        self::assertInstanceOf(Between::class, $negated);
        self::assertFalse($plain->negated);
        self::assertTrue($negated->negated);
        self::assertInstanceOf(ColumnUse::class, $plain->operand);
        self::assertSame('SELECT a BETWEEN 1 AND 2, a NOT BETWEEN 1 AND 2 FROM t', $operation->toString());
    }

    public function testExpressionLowersMembershipTestsAgainstAListAQueryAndATable(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT a IN (1, 2), a NOT IN (), a IN (SELECT 1), a NOT IN main.u FROM t');

        self::assertInstanceOf(Select::class, $operation->statement);
        $expressions = array_map(static function (object $column): object {
            self::assertInstanceOf(ResultColumn::class, $column);

            return $column->expression;
        }, $operation->statement->columns);
        self::assertInstanceOf(InList::class, $expressions[0]);
        self::assertCount(2, $expressions[0]->items);
        self::assertFalse($expressions[0]->negated);
        self::assertInstanceOf(InList::class, $expressions[1]);
        self::assertSame([], $expressions[1]->items);
        self::assertTrue($expressions[1]->negated);
        self::assertInstanceOf(InQuery::class, $expressions[2]);
        self::assertInstanceOf(Select::class, $expressions[2]->query);
        self::assertInstanceOf(InTable::class, $expressions[3]);
        self::assertSame('u', $expressions[3]->table->name->value);
        self::assertSame('main', $expressions[3]->table->schema?->value);
        self::assertTrue($expressions[3]->negated);
        self::assertSame('SELECT a IN (1, 2), a NOT IN (), a IN (SELECT 1), a NOT IN main.u FROM t', $operation->toString());
    }

    public function testMatchLowersEachPatternOperatorWithItsNegation(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze("SELECT a LIKE 'x', a NOT LIKE 'x', a GLOB 'x', a NOT REGEXP 'x', a MATCH 'x' FROM t");

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame([[PatternOperator::Like, false], [PatternOperator::Like, true], [PatternOperator::Glob, false], [PatternOperator::Regexp, true], [PatternOperator::Match, false]], array_map(static function (object $column): array {
            self::assertInstanceOf(ResultColumn::class, $column);
            self::assertInstanceOf(PatternMatch::class, $column->expression);
            self::assertNull($column->expression->escape);

            return [$column->expression->operator, $column->expression->negated];
        }, $operation->statement->columns));
        self::assertSame("SELECT a LIKE 'x', a NOT LIKE 'x', a GLOB 'x', a NOT REGEXP 'x', a MATCH 'x' FROM t", $operation->toString());
    }

    public function testMatchKeepsTheEscapeOperand(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze("SELECT a LIKE 'x' ESCAPE '!', a NOT GLOB b ESCAPE c FROM t");

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(ResultColumn::class, $operation->statement->columns[0]);
        self::assertInstanceOf(ResultColumn::class, $operation->statement->columns[1]);
        $like = $operation->statement->columns[0]->expression;
        $glob = $operation->statement->columns[1]->expression;
        self::assertInstanceOf(PatternMatch::class, $like);
        self::assertInstanceOf(PatternMatch::class, $glob);
        self::assertNotNull($like->escape);
        self::assertInstanceOf(ColumnUse::class, $glob->escape);
        self::assertSame('c', $glob->escape->name->value);
        self::assertTrue($glob->negated);
        self::assertSame("SELECT a LIKE 'x' ESCAPE '!', a NOT GLOB b ESCAPE c FROM t", $operation->toString());
    }

    public function testNegatedTellsWhetherNotIsWrittenBeforeBetweenOrIn(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT 1 BETWEEN 0 AND 2, 1 NOT BETWEEN 0 AND 2, 1 IN (1), 1 NOT IN (1)');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame([false, true, false, true], array_map(static function (object $column): ?bool {
            self::assertInstanceOf(ResultColumn::class, $column);
            $predicate = $column->expression;

            return $predicate instanceof Between || $predicate instanceof InList ? $predicate->negated : null;
        }, $operation->statement->columns));
    }
}
