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
use SqlSemantics\Platform\Sqlite\Statement\Schema\Limit\DefinitionPosition;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Limit\ProhibitedConstruct;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Limit\ProhibitedExpression;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(Limits::class)]
#[Medium]
final class LimitsTest extends TestCase
{
    public function testNodesWalksTheExpressionInOrderWithoutEnteringASubquery(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT a + (SELECT b + ? FROM t) - c');
        $classes = array_map(static fn (object $node): string => substr($node::class, (int) strrpos($node::class, '\\') + 1), (new Limits())->nodes($query->field(0)->expression));

        self::assertSame(['Binary', 'Binary', 'ColumnUse', 'ScalarSubquery', 'Select', 'ColumnUse'], $classes);
    }

    public function testConstructClassifiesEachRejectedNode(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT ?, (SELECT 1), 1 IN t, t.a, random(), RANDOMBLOB(1), CURRENT_TIME, abs(1), a + 1');
        $limits = new Limits();
        $constructs = array_map(static fn (object $field): ?ProhibitedConstruct => $limits->construct($field->expression), iterator_to_array($query->fields() ?? []));

        self::assertSame([ProhibitedConstruct::Parameter, ProhibitedConstruct::Subquery, ProhibitedConstruct::Subquery, ProhibitedConstruct::DotOperator, ProhibitedConstruct::NonDeterministicFunction, ProhibitedConstruct::NonDeterministicFunction, ProhibitedConstruct::NonDeterministicFunction, null, null], array_values($constructs));
        self::assertNull($limits->construct(new ColumnUse(new Name('a'))));
        self::assertSame(ProhibitedConstruct::DotOperator, $limits->construct(new ColumnUse(new Name('a'), new QualifiedName(new Name('t')))));
    }

    public function testConstructsAnswersWhatThePositionRejectsOnceEach(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT t.a + ? + ? + random() + (SELECT 1) + current_date');
        $expression = $query->field(0)->expression;
        $limits = new Limits();

        self::assertSame([ProhibitedConstruct::Parameter, ProhibitedConstruct::Subquery], $limits->constructs($expression, DefinitionPosition::CheckConstraint));
        self::assertSame([ProhibitedConstruct::Parameter, ProhibitedConstruct::NonDeterministicFunction, ProhibitedConstruct::Subquery], $limits->constructs($expression, DefinitionPosition::PartialIndexWhere));
        self::assertSame([ProhibitedConstruct::DotOperator, ProhibitedConstruct::Parameter, ProhibitedConstruct::NonDeterministicFunction, ProhibitedConstruct::Subquery], $limits->constructs($expression, DefinitionPosition::IndexExpression));
        self::assertSame([ProhibitedConstruct::DotOperator, ProhibitedConstruct::Parameter, ProhibitedConstruct::NonDeterministicFunction, ProhibitedConstruct::Subquery], $limits->constructs($expression, DefinitionPosition::GeneratedColumn));
    }

    public function testReportRecordsOneDiagnosticPerRejectedConstruct(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('SELECT a > ? AND b IN (SELECT 1)');
        $derivation = new Derivation($semantics->context());
        (new Limits())->report($query->field(0)->expression, DefinitionPosition::CheckConstraint, $derivation);
        $diagnostics = $derivation->facts()->diagnostics;

        self::assertCount(2, $diagnostics);
        self::assertInstanceOf(ProhibitedExpression::class, $diagnostics[0]);
        self::assertSame(ProhibitedConstruct::Parameter, $diagnostics[0]->construct);
        self::assertSame(DefinitionPosition::CheckConstraint, $diagnostics[0]->position);
    }

    public function testNonConstantFindsWhatADefaultMayNotContain(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT ?, (SELECT 1), 1 IN t, a, "a", RAISE(IGNORE), random() + abs(1), CASE WHEN 1 THEN 2 END, TRUE, CURRENT_TIME');
        $limits = new Limits();
        $constant = array_map(static fn (object $field): bool => $limits->nonConstant($field->expression), iterator_to_array($query->fields() ?? []));

        self::assertSame([true, true, true, true, true, true, false, false, false, false], array_values($constant));
    }
}
