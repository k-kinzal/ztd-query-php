<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\UpdateAssignment;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;

#[CoversClass(UpdateAssignment::class)]
#[Small]
final class UpdateAssignmentTest extends TestCase
{
    public function testReadSlotMustUseTheTargetOccurrence(): void
    {
        $this->expectExceptionMessage('read slot must belong to occurrence zero');

        new UpdateAssignment(new ColumnReference(0), new ColumnReference(0, 1));
    }

    public function testRetainsTheTargetAndNullOperand(): void
    {
        $target = new ColumnReference(2);
        $assignment = new UpdateAssignment($target, new NullLiteral());

        self::assertSame($target, $assignment->target);
        self::assertInstanceOf(NullLiteral::class, $assignment->value);
    }
}
