<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\PostgreSql\Lowering\Expression\Expressions;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnStar;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\RowSpelling;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\FieldSelection;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Indirection;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\UnaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Step\Slice;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\PositionalParameter;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Expressions::class)]
#[Small]
final class ExpressionsTest extends TestCase
{
    public function testExpressionLowersOperatorsByPrecedence(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $expression = $lowering->expressions->expression((new PostgreSqlParser('pg-17.2'))->parse('SELECT a + 1 * 2 FROM t')->find('a_expr')[0]);
        self::assertInstanceOf(BinaryOperation::class, $expression);
        self::assertSame('+', $expression->operator->name->value);
        self::assertInstanceOf(BinaryOperation::class, $expression->right);
    }

    public function testExpressionsFlattensAnExpressionList(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $expressions = $lowering->expressions->expressions((new PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FROM t WHERE a IN (1, 2, $3)')->find('expr_list')[0]);
        self::assertCount(3, $expressions);
        self::assertInstanceOf(PositionalParameter::class, $expressions[2]);
    }

    public function testColumnReferenceKeepsThePartsAsWritten(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $reference = $lowering->expressions->columnReference((new PostgreSqlParser('pg-17.2'))->parse('SELECT s.t.A FROM s.t')->find('columnref')[0]);
        self::assertInstanceOf(ColumnReference::class, $reference);
        self::assertSame(['s', 't', 'a'], array_map(static fn (Name $part): string => $part->value, $reference->parts));
    }

    public function testColumnReferenceLowersAStar(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $reference = $lowering->expressions->columnReference((new PostgreSqlParser('pg-17.2'))->parse('SELECT f(t.*) FROM t')->find('columnref')[0]);
        self::assertInstanceOf(ColumnStar::class, $reference);
    }

    public function testIndirectionLowersStepsInOrder(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $steps = $lowering->expressions->indirection((new PostgreSqlParser('pg-17.2'))->parse('SELECT t.a[1:].b FROM t')->find('indirection')[0]);
        self::assertCount(3, $steps);
        self::assertInstanceOf(Slice::class, $steps[1]);
        self::assertInstanceOf(FieldSelection::class, $steps[2]);
    }

    public function testComparisonLowersTheOperatorToken(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $node = (new PostgreSqlParser('pg-17.2'))->parse('SELECT 1 <> 2')->find('a_expr')[0];
        self::assertSame('<>', $lowering->expressions->comparison(new Form($node, 'a_expr: a_expr NOT_EQUALS a_expr'))->operator->name->value);
    }

    public function testParameterAppliesTheSteps(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $node = (new PostgreSqlParser('pg-17.2'))->parse('SELECT $1[2]')->find('c_expr')[0];
        self::assertInstanceOf(Indirection::class, $lowering->expressions->parameter(new Form($node, 'c_expr: PARAM opt_indirection')));
    }

    public function testNegationBuildsAPrefixMinus(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $minus = $lowering->productions->form((new PostgreSqlParser('pg-17.2'))->parse('SELECT -1')->find('a_expr')[0])->token(0);
        $negation = $lowering->expressions->negation($minus, new Constant(new IntegerConstant('1')));
        self::assertInstanceOf(UnaryOperation::class, $negation);
        self::assertSame('-', $negation->operator->name->value);
    }

    public function testRowLowersTheFields(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        self::assertCount(0, $lowering->expressions->row((new PostgreSqlParser('pg-17.2'))->parse('SELECT ROW()')->find('explicit_row')[0]));
    }

    public function testRowConstructorKeepsTheSpelling(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        self::assertSame(RowSpelling::Implicit, $lowering->expressions->rowConstructor((new PostgreSqlParser('pg-17.2'))->parse('SELECT (1, 2)')->find('implicit_row')[0])->spelling);
    }

    public function testCaseExpressionLowersTheBranches(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $case = $lowering->expressions->caseExpression((new PostgreSqlParser('pg-17.2'))->parse('SELECT CASE WHEN true THEN 1 WHEN false THEN 2 ELSE 3 END')->find('case_expr')[0]);
        self::assertCount(2, $case->branches);
        self::assertNull($case->operand);
    }

    public function testArrayLiteralLowersNestedLevels(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $items = $lowering->expressions->arrayLiteral((new PostgreSqlParser('pg-17.2'))->parse('SELECT ARRAY[[1], [2, 3]]')->find('array_expr')[0]);
        self::assertCount(2, $items->nested);
        self::assertCount(2, $items->nested[1]->values);
    }
}
