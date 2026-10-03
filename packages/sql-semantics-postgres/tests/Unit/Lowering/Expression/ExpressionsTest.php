<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\PostgreSql\Lowering\Expression\Expressions;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\FieldSelection;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\PositionalParameter;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Expressions::class)]
#[Small]
final class ExpressionsTest extends TestCase
{
    public function testExpressionLowersAComparisonOfAColumnAndAConstant(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT a <= 1 FROM t');
        $expression = $lowering->expressions->expression($tree->find('a_expr')[0]);
        self::assertInstanceOf(BinaryOperation::class, $expression);
        self::assertSame('<=', $expression->operator->name->value);
        self::assertInstanceOf(ColumnReference::class, $expression->left);
        self::assertInstanceOf(Constant::class, $expression->right);
    }

    public function testExpressionReportsAFormOutsideTheSlice(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT a + 1 FROM t');
        $this->expectExceptionMessage('No semantic rule is implemented for: a_expr: a_expr + a_expr');
        $lowering->expressions->expression($tree->find('a_expr')[0]);
    }

    public function testExpressionsFlattensAnExpressionList(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FROM t WHERE a IN (1, 2, $3)');
        $expressions = $lowering->expressions->expressions($tree->find('expr_list')[0]);
        self::assertCount(3, $expressions);
        self::assertInstanceOf(PositionalParameter::class, $expressions[2]);
    }

    public function testColumnReferenceKeepsThePartsAsWritten(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT s.t.A FROM s.t');
        $reference = $lowering->expressions->columnReference($tree->find('columnref')[0]);
        self::assertInstanceOf(ColumnReference::class, $reference);
        self::assertSame(['s', 't', 'a'], array_map(static fn (Name $part): string => $part->value, $reference->parts));
    }

    public function testColumnReferenceReportsAStar(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT t.* FROM t');
        $this->expectExceptionMessage('No semantic rule is implemented for: indirection_el: . *');
        $lowering->expressions->columnReference($tree->find('columnref')[0]);
    }

    public function testIndirectionLowersFieldSelectionsInOrder(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT t.a.b FROM t');
        $steps = $lowering->expressions->indirection($tree->find('indirection')[0]);
        self::assertCount(2, $steps);
        self::assertInstanceOf(FieldSelection::class, $steps[1]);
        self::assertSame('b', $steps[1]->field()->value);
    }

    public function testIndirectionIsEmptyForAnAbsentOptionalIndirection(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT $1');
        self::assertSame([], $lowering->expressions->indirection($tree->find('opt_indirection')[0]));
    }

    public function testComparisonBuildsTheOperatorFromTheToken(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT 1 != 2');
        $comparison = $lowering->expressions->comparison($lowering->productions->form($tree->find('a_expr')[0]));
        self::assertSame('<>', $comparison->operator->name->value);
    }

    public function testParameterLowersAPositionalParameter(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT $007');
        $parameter = $lowering->expressions->parameter($lowering->productions->form($tree->find('c_expr')[0]));
        self::assertInstanceOf(PositionalParameter::class, $parameter);
        self::assertSame('7', $parameter->number);
    }

    public function testParameterReportsAnIndirectionAfterTheMarker(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT $1.a');
        $this->expectExceptionMessage('No semantic rule is implemented for: c_expr: PARAM opt_indirection');
        $lowering->expressions->parameter($lowering->productions->form($tree->find('c_expr')[0]));
    }
}
