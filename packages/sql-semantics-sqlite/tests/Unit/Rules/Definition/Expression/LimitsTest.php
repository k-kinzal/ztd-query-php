<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Definition\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Rules\Definition\Expression\Limits;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\RowExpression;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Limit\DefinitionPosition;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Limit\ProhibitedConstruct;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Limit\ProhibitedExpression;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Scalar;

#[CoversClass(Limits::class)]
#[Medium]
final class LimitsTest extends TestCase
{
    public function testNodesWalksTheExpressionInOrderWithoutEnteringASubquery(): void
    {
        $expression = (new Semantics(Dialect::Sqlite))->analyze('SELECT a + (SELECT b + ? FROM t) - c')->field(0)->expression;

        self::assertNotNull($expression);
        $classes = array_map(static fn (object $node): string => substr($node::class, (int) strrpos($node::class, '\\') + 1), (new Limits())->nodes($expression));

        self::assertSame(['Binary', 'Binary', 'ColumnUse', 'ScalarSubquery', 'Select', 'ColumnUse'], $classes);
    }

    public function testClassifyNamesEachRejectedNode(): void
    {
        $row = (new Semantics(Dialect::Sqlite))->analyze('SELECT (?, (SELECT 1), 1 IN t, t.a, random(), RANDOMBLOB(1), CURRENT_TIME, abs(1), a + 1, EXISTS (SELECT 1), a IN (SELECT 1))')->field(0)->expression;
        $limits = new Limits();

        self::assertInstanceOf(RowExpression::class, $row);
        $constructs = array_map(static fn (Scalar $item): ?ProhibitedConstruct => $limits->classify($item), $row->items);
        self::assertSame([ProhibitedConstruct::Parameter, ProhibitedConstruct::Subquery, ProhibitedConstruct::Subquery, ProhibitedConstruct::DotOperator, ProhibitedConstruct::NonDeterministicFunction, ProhibitedConstruct::NonDeterministicFunction, ProhibitedConstruct::NonDeterministicFunction, null, null, ProhibitedConstruct::Subquery, ProhibitedConstruct::Subquery], $constructs);
        self::assertNull($limits->classify(new ColumnUse(new Name('a'))));
        self::assertSame(ProhibitedConstruct::DotOperator, $limits->classify(new ColumnUse(new Name('a'), new QualifiedName(new Name('t')))));
    }

    public function testRejectedAnswersWhatThePositionRejectsOnceEach(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT t.a + ? + ? + random() + (SELECT 1) + current_date');
        $expression = $query->field(0)->expression;
        $limits = new Limits();

        self::assertNotNull($expression);
        self::assertSame([ProhibitedConstruct::Parameter, ProhibitedConstruct::Subquery], $limits->rejected($expression, DefinitionPosition::CheckConstraint));
        self::assertSame([ProhibitedConstruct::Parameter, ProhibitedConstruct::NonDeterministicFunction, ProhibitedConstruct::Subquery], $limits->rejected($expression, DefinitionPosition::PartialIndexWhere));
        self::assertSame([ProhibitedConstruct::DotOperator, ProhibitedConstruct::Parameter, ProhibitedConstruct::NonDeterministicFunction, ProhibitedConstruct::Subquery], $limits->rejected($expression, DefinitionPosition::IndexExpression));
        self::assertSame([ProhibitedConstruct::DotOperator, ProhibitedConstruct::Parameter, ProhibitedConstruct::NonDeterministicFunction, ProhibitedConstruct::Subquery], $limits->rejected($expression, DefinitionPosition::GeneratedColumn));
    }

    public function testReportRecordsOneDiagnosticPerRejectedConstruct(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $expression = $semantics->analyze('SELECT a > ? AND b IN (SELECT 1)')->field(0)->expression;
        $derivation = new Derivation($semantics->context());

        self::assertNotNull($expression);
        (new Limits())->report($expression, DefinitionPosition::CheckConstraint, $derivation);
        $diagnostics = $derivation->facts()->diagnostics;

        self::assertCount(2, $diagnostics);
        self::assertInstanceOf(ProhibitedExpression::class, $diagnostics[0]);
        self::assertSame(ProhibitedConstruct::Parameter, $diagnostics[0]->construct);
        self::assertSame(DefinitionPosition::CheckConstraint, $diagnostics[0]->position);
    }

    public function testNonConstantFindsWhatADefaultMayNotContain(): void
    {
        $row = (new Semantics(Dialect::Sqlite))->analyze('SELECT (?, (SELECT 1), 1 IN t, a, "a", RAISE(IGNORE), random() + abs(1), CASE WHEN 1 THEN 2 END, TRUE, CURRENT_TIME)')->field(0)->expression;
        $limits = new Limits();

        self::assertInstanceOf(RowExpression::class, $row);
        $constant = array_map(static fn (Scalar $item): bool => $limits->nonConstant($item), $row->items);
        self::assertSame([true, true, true, true, true, true, false, false, false, false], $constant);
    }
}
