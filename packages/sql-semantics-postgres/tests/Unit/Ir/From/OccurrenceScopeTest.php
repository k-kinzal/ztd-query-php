<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\From\DerivedSource;
use SqlSemantics\Platform\PostgreSql\Ir\From\OccurrenceScope;
use SqlSemantics\Platform\PostgreSql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerOperator;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\LogicalOperator;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\LogicalPredicate;
use SqlSemantics\Platform\PostgreSql\Ir\ResultColumn;
use SqlSemantics\Platform\PostgreSql\Ir\Select;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;

#[CoversClass(OccurrenceScope::class)]
#[Small]
final class OccurrenceScopeTest extends TestCase
{
    public function testPredicateRejectsAValidColumnNumberAtAnInvisibleOccurrence(): void
    {
        $table = new TableSource(new Identifier('public'), new Identifier('a'), [new Identifier('id')]);
        $outside = new IntegerComparison(IntegerOperator::from('='), new IntegerLiteral('1'), new ColumnReference(0, 1));
        $this->expectExceptionMessage('must belong to a visible input occurrence');

        (new OccurrenceScope())->predicate($outside, [$table]);
    }

    public function testCheckTreeCountsBothOccurrencesOfTheSameChild(): void
    {
        $operand = new BooleanLiteral(true);
        $condition = new LogicalPredicate(LogicalOperator::And, $operand, $operand);

        self::assertSame(3, (new OccurrenceScope())->checkTree($condition, [], 'invalid slot', 0, 0));
    }

    public function testHasSlotUsesTheDerivedOutputLayout(): void
    {
        $derived = new DerivedSource(new Select([new ResultColumn(new IntegerLiteral('1'), new Identifier('id'))]));
        $scope = new OccurrenceScope();

        self::assertTrue($scope->hasSlot($derived, 0));
        self::assertFalse($scope->hasSlot($derived, 1));
        self::assertFalse($scope->hasSlot(null, 0));
    }
    public function testColumnRejectsTheWrongNativeKind(): void
    {
        $table = new TableSource(new Identifier('public'), new Identifier('messages'), [new Identifier('label')], false, [ColumnKind::Text]);
        $scope = new OccurrenceScope();
        $scope->column(new TextColumnReference(0), [$table]);
        $this->expectExceptionMessage('Column slot must belong to the SELECT source.');

        $scope->column(new ColumnReference(0), [$table]);
    }
}
