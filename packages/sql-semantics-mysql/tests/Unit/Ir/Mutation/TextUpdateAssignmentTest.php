<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\MySql\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\MySql\Ir\Mutation\TextUpdateAssignment;

#[CoversClass(TextUpdateAssignment::class)]
#[Small]
final class TextUpdateAssignmentTest extends TestCase
{
    public function testStoresTheExactWriteSlotAndText(): void
    {
        $target = new TextColumnReference(2);
        $value = new TextLiteral("a\0 ");
        $assignment = new TextUpdateAssignment($target, $value);

        self::assertSame($target, $assignment->target);
        self::assertSame($value, $assignment->value);
    }

    public function testRejectsAReadOutsideTheTargetRow(): void
    {
        $this->expectExceptionMessage('A VARCHAR UPDATE read slot must belong to occurrence zero.');

        new TextUpdateAssignment(new TextColumnReference(0), new TextColumnReference(1, 2));
    }
}
