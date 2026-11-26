<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ComparisonOperator;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate\CheckConstraint;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate\ComparisonSpelling;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate\ImplicitCheckLabel;

#[CoversClass(CheckConstraint::class)]
#[Small]
final class CheckConstraintTest extends TestCase
{
    public function testOwningSlotIsIndependentOfTheReferencedSlot(): void
    {
        $condition = new ColumnReference(1);
        $constraint = new CheckConstraint(new Identifier('another slot'), $condition, 0);

        self::assertSame($condition, $constraint->condition);
        self::assertSame(0, $constraint->column);
    }

    public function testCheckPresentationRejectsANameOfAnotherSlot(): void
    {
        $condition = new Comparison(ComparisonOperator::Greater, new ColumnReference(0), new IntegerLiteral('0'));
        $label = new ImplicitCheckLabel(new Identifier('b'), ComparisonSpelling::Greater, false);
        $check = new CheckConstraint($label, $condition, 0);
        $this->expectExceptionMessage('must identify the resolved column');

        $check->checkPresentation([new Identifier('a'), new Identifier('b')]);
    }
}
