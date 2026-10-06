<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Lowering\Expression\ExpressionRule;
use SqlSemantics\Platform\Sqlite\Statement\Expression\BindParameter;
use SqlSemantics\Platform\Sqlite\Statement\Expression\CaseExpression;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Cast;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Collate;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\DoubleQuotedWord;
use SqlSemantics\Platform\Sqlite\Statement\Expression\FunctionCall;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\BinaryOperator;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ParameterPrefix;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Raise;
use SqlSemantics\Platform\Sqlite\Statement\Expression\RaiseAction;
use SqlSemantics\Platform\Sqlite\Statement\Expression\RowExpression;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery\Exists;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery\InTable;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\Sqlite\Statement\Expression\TruthWord;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValuesClause;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\CreateTrigger;

#[CoversClass(ExpressionRule::class)]
#[Medium]
final class ExpressionRuleTest extends TestCase
{
    public function testExpressionKeepsEveryParenthesisPairAsAGrouping(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT (1 + 2) * 3, ((4))');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(ResultColumn::class, $operation->statement->columns[0]);
        self::assertInstanceOf(ResultColumn::class, $operation->statement->columns[1]);
        $product = $operation->statement->columns[0]->expression;
        self::assertInstanceOf(Binary::class, $product);
        self::assertSame(BinaryOperator::Multiply, $product->operator);
        self::assertInstanceOf(Grouped::class, $product->left);
        self::assertInstanceOf(Binary::class, $product->left->operand);
        self::assertSame(BinaryOperator::Add, $product->left->operand->operator);
        $twice = $operation->statement->columns[1]->expression;
        self::assertInstanceOf(Grouped::class, $twice);
        self::assertInstanceOf(Grouped::class, $twice->operand);
        self::assertInstanceOf(IntegerLiteral::class, $twice->operand->operand);
        self::assertSame('SELECT (1 + 2) * 3, ((4))', $operation->toString());
    }

    public function testExpressionLowersCollateCastAndRowExpressions(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze("SELECT a COLLATE nocase, CAST(a AS TEXT), (1, 'x') = (2, 'y') FROM t");

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame([Collate::class, Cast::class, Binary::class], array_map(static function (object $column): string {
            self::assertInstanceOf(ResultColumn::class, $column);

            return $column->expression::class;
        }, $operation->statement->columns));
        self::assertInstanceOf(ResultColumn::class, $operation->statement->columns[2]);
        $comparison = $operation->statement->columns[2]->expression;
        self::assertInstanceOf(Binary::class, $comparison);
        self::assertInstanceOf(RowExpression::class, $comparison->left);
        self::assertCount(2, $comparison->left->items);
        self::assertInstanceOf(TextLiteral::class, $comparison->left->items[1]);
        self::assertSame("SELECT a COLLATE nocase, CAST(a AS TEXT), (1, 'x') = (2, 'y') FROM t", $operation->toString());
    }

    public function testExpressionLowersAScalarSubqueryAndAnExistsTest(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT (SELECT 1), EXISTS (SELECT 1 FROM t)');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(ResultColumn::class, $operation->statement->columns[0]);
        self::assertInstanceOf(ResultColumn::class, $operation->statement->columns[1]);
        self::assertInstanceOf(ScalarSubquery::class, $operation->statement->columns[0]->expression);
        self::assertInstanceOf(Select::class, $operation->statement->columns[0]->expression->query);
        self::assertInstanceOf(Exists::class, $operation->statement->columns[1]->expression);
        self::assertInstanceOf(Select::class, $operation->statement->columns[1]->expression->query);
        self::assertSame('SELECT (SELECT 1), EXISTS (SELECT 1 FROM t)', $operation->toString());
    }

    public function testExpressionLowersRaiseWithAndWithoutAMessage(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze("CREATE TRIGGER tr INSERT ON t BEGIN SELECT RAISE(IGNORE), RAISE(ABORT, 'no'); END");

        self::assertInstanceOf(CreateTrigger::class, $operation->statement);
        $select = $operation->statement->steps[0];
        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(ResultColumn::class, $select->columns[0]);
        self::assertInstanceOf(ResultColumn::class, $select->columns[1]);
        $ignore = $select->columns[0]->expression;
        $abort = $select->columns[1]->expression;
        self::assertInstanceOf(Raise::class, $ignore);
        self::assertInstanceOf(Raise::class, $abort);
        self::assertSame(RaiseAction::Ignore, $ignore->action);
        self::assertNull($ignore->message);
        self::assertSame(RaiseAction::Abort, $abort->action);
        self::assertInstanceOf(TextLiteral::class, $abort->message);
        self::assertSame('no', $abort->message->value);
        self::assertSame("CREATE TRIGGER tr INSERT ON t BEGIN SELECT RAISE(IGNORE), RAISE(ABORT, 'no'); END", $operation->toString());
    }

    public function testTermLowersALiteralTerm(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT 7');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(ResultColumn::class, $operation->statement->columns[0]);
        self::assertInstanceOf(IntegerLiteral::class, $operation->statement->columns[0]->expression);
        self::assertSame('7', $operation->statement->columns[0]->expression->digits);
    }

    public function testWordLowersAnUnqualifiedWordByHowItIsWritten(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT "w" AS c1, TRUE AS c2, false AS c3, a AS c4, [true] AS c5, `false` AS c6 FROM t');

        self::assertInstanceOf(Select::class, $operation->statement);
        $expressions = array_map(static function (object $column): object {
            self::assertInstanceOf(ResultColumn::class, $column);

            return $column->expression;
        }, $operation->statement->columns);
        self::assertInstanceOf(DoubleQuotedWord::class, $expressions[0]);
        self::assertSame('w', $expressions[0]->word->value);
        self::assertInstanceOf(TruthWord::class, $expressions[1]);
        self::assertTrue($expressions[1]->value);
        self::assertInstanceOf(TruthWord::class, $expressions[2]);
        self::assertFalse($expressions[2]->value);
        self::assertInstanceOf(ColumnUse::class, $expressions[3]);
        self::assertSame('a', $expressions[3]->name->value);
        self::assertNull($expressions[3]->qualifier);
        self::assertInstanceOf(ColumnUse::class, $expressions[4]);
        self::assertSame('true', $expressions[4]->name->value);
        self::assertInstanceOf(ColumnUse::class, $expressions[5]);
        self::assertSame('SELECT "w" AS c1, TRUE AS c2, FALSE AS c3, a AS c4, `true` AS c5, `false` AS c6 FROM t', $operation->toString());
    }

    public function testColumnLowersTableAndSchemaQualifiedColumnUses(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT t.a, main.t.b FROM t');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(ResultColumn::class, $operation->statement->columns[0]);
        self::assertInstanceOf(ResultColumn::class, $operation->statement->columns[1]);
        $table = $operation->statement->columns[0]->expression;
        $schema = $operation->statement->columns[1]->expression;
        self::assertInstanceOf(ColumnUse::class, $table);
        self::assertInstanceOf(ColumnUse::class, $schema);
        self::assertSame('a', $table->name->value);
        self::assertSame('t', $table->qualifier?->name->value);
        self::assertNull($table->qualifier->schema);
        self::assertSame('b', $schema->name->value);
        self::assertSame('t', $schema->qualifier?->name->value);
        self::assertSame('main', $schema->qualifier->schema?->value);
        self::assertSame('SELECT t.a, main.t.b FROM t', $operation->toString());
    }

    public function testParameterLowersEveryPrefixWithItsLabel(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT ?, ?1, :x, @y, $z');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame([[ParameterPrefix::Question, ''], [ParameterPrefix::Question, '1'], [ParameterPrefix::Colon, 'x'], [ParameterPrefix::At, 'y'], [ParameterPrefix::Dollar, 'z']], array_map(static function (object $column): array {
            self::assertInstanceOf(ResultColumn::class, $column);
            self::assertInstanceOf(BindParameter::class, $column->expression);

            return [$column->expression->prefix, $column->expression->label];
        }, $operation->statement->columns));
        self::assertSame('SELECT ?, ?1, :x, @y, $z', $operation->toString());
    }

    public function testConditionalLowersTheBaseTheBranchesAndTheElsePart(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT CASE a WHEN 1 THEN 2 WHEN 3 THEN 4 ELSE 5 END, CASE WHEN a THEN 1 END FROM t');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(ResultColumn::class, $operation->statement->columns[0]);
        self::assertInstanceOf(ResultColumn::class, $operation->statement->columns[1]);
        $full = $operation->statement->columns[0]->expression;
        $bare = $operation->statement->columns[1]->expression;
        self::assertInstanceOf(CaseExpression::class, $full);
        self::assertInstanceOf(CaseExpression::class, $bare);
        self::assertInstanceOf(ColumnUse::class, $full->base);
        self::assertCount(2, $full->branches);
        self::assertInstanceOf(IntegerLiteral::class, $full->branches[1]->when);
        self::assertSame('3', $full->branches[1]->when->digits);
        self::assertInstanceOf(IntegerLiteral::class, $full->branches[1]->then);
        self::assertSame('4', $full->branches[1]->then->digits);
        self::assertInstanceOf(IntegerLiteral::class, $full->otherwise);
        self::assertNull($bare->base);
        self::assertCount(1, $bare->branches);
        self::assertNull($bare->otherwise);
        self::assertSame('SELECT CASE a WHEN 1 THEN 2 WHEN 3 THEN 4 ELSE 5 END, CASE WHEN a THEN 1 END FROM t', $operation->toString());
    }

    public function testListLowersAnEmptyAndAFilledExpressionList(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT random(), max(1, 2, 3)');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame([0, 3], array_map(static function (object $column): int {
            self::assertInstanceOf(ResultColumn::class, $column);
            self::assertInstanceOf(FunctionCall::class, $column->expression);

            return count($column->expression->arguments);
        }, $operation->statement->columns));
    }

    public function testItemsLowersTheExpressionsOfARowInWrittenOrder(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze("VALUES (1, 'two', 3.0)");

        self::assertInstanceOf(ValuesClause::class, $operation->statement);
        self::assertSame([IntegerLiteral::class, TextLiteral::class, 'SqlSemantics\\Platform\\Sqlite\\Statement\\Expression\\Literal\\RealLiteral'], array_map(static fn (object $value): string => $value::class, $operation->statement->rows[0]->values));
    }

    public function testOptionalListIsNullWithoutParenthesesAfterATableValuedIn(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT a IN t, a IN f(), a IN f(1, 2) FROM u');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame([null, 0, 2], array_map(static function (object $column): ?int {
            self::assertInstanceOf(ResultColumn::class, $column);
            self::assertInstanceOf(InTable::class, $column->expression);

            return $column->expression->arguments === null ? null : count($column->expression->arguments);
        }, $operation->statement->columns));
        self::assertSame('SELECT a IN t, a IN f(), a IN f(1, 2) FROM u', $operation->toString());
    }

    public function testWhereIsNullWithoutTheClause(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $bare = $semantics->analyze('SELECT 1 FROM t')->statement;
        $filtered = $semantics->analyze('SELECT 1 FROM t WHERE a = 1')->statement;

        self::assertInstanceOf(Select::class, $bare);
        self::assertInstanceOf(Select::class, $filtered);
        self::assertNull($bare->where);
        self::assertInstanceOf(Binary::class, $filtered->where);
        self::assertSame(BinaryOperator::Equal, $filtered->where->operator);
    }
}
