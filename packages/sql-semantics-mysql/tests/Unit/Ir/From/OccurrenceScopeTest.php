<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\BooleanLiteral;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\ColumnKind;
use SqlSemantics\Platform\MySql\Ir\From\DerivedSource;
use SqlSemantics\Platform\MySql\Ir\From\IntegralColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\IntegralSlot;
use SqlSemantics\Platform\MySql\Ir\From\OccurrenceScope;
use SqlSemantics\Platform\MySql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\Varchar;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Mutation\TextUpdateAssignment;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerOperator;
use SqlSemantics\Platform\MySql\Ir\Predicate\LogicalOperator;
use SqlSemantics\Platform\MySql\Ir\Predicate\LogicalPredicate;
use SqlSemantics\Platform\MySql\Ir\ResultColumn;
use SqlSemantics\Platform\MySql\Ir\Schema\IntegerStorage;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Ir\TableSource;

#[CoversClass(OccurrenceScope::class)]
#[Small]
final class OccurrenceScopeTest extends TestCase
{
    public function testPredicateRejectsAValidColumnNumberAtAnInvisibleOccurrence(): void
    {
        $table = new TableSource(new Identifier('ir_fixture'), new Identifier('a'), [new Identifier('id')]);
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

    public function testResultRejectsAnOrdinarySlotForAnExtendedIntegralField(): void
    {
        $table = new TableSource(
            new Identifier('app'),
            new Identifier('fields'),
            [new Identifier('small'), new Identifier('wide')],
            [ColumnKind::SignedInt, new IntegralSlot(IntegerStorage::Wide, true)],
        );
        $scope = new OccurrenceScope();
        self::assertSame(1, $scope->result(new IntegralColumnReference(1), [$table], 0, 0));
        $this->expectExceptionMessage('An integral slot must belong to the SELECT source.');

        $scope->result(new IntegralColumnReference(0), [$table], 0, 0);
    }

    public function testHasSlotUsesTheDerivedOutputLayout(): void
    {
        $derived = new DerivedSource(new Select([new ResultColumn(new IntegerLiteral('1'), new Identifier('id'))]));
        $scope = new OccurrenceScope();

        self::assertTrue($scope->hasSlot($derived, 0));
        self::assertFalse($scope->hasSlot($derived, 1));
        self::assertFalse($scope->hasSlot(null, 0));
    }

    public function testTextColumnRejectsTheSignedNeighborAtTheSameOccurrence(): void
    {
        $source = new TableSource(
            new Identifier('app'),
            new Identifier('items'),
            [new Identifier('label'), new Identifier('id')],
            [new Varchar(8), ColumnKind::SignedInt],
        );
        $scope = new OccurrenceScope();
        $scope->textColumn(new TextColumnReference(0), [$source]);
        $this->expectExceptionMessage('A VARCHAR slot must belong to a visible physical input occurrence.');

        $scope->textColumn(new TextColumnReference(1), [$source]);
    }

    public function testUpdateAssignmentRejectsAnIntegerSlotInATextRead(): void
    {
        $table = new TableSource(new Identifier('app'), new Identifier('t'), [new Identifier('label'), new Identifier('id')], [new Varchar(2), ColumnKind::SignedInt]);
        $assignment = new TextUpdateAssignment(new TextColumnReference(0), new TextColumnReference(1));
        $this->expectExceptionMessage('A VARCHAR slot must belong to a visible physical input occurrence.');

        (new OccurrenceScope())->updateAssignment($assignment, $table);
    }
}
