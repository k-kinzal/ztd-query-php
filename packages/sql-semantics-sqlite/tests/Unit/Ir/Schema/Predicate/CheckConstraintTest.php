<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate\CheckConstraint;

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
}
