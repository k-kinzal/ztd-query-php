<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\UpdateAssignment;

/**
 * @covers \SqlSemantics\Platform\Sqlite\Ir\Mutation\UpdateAssignment
 */
final class UpdateAssignmentTest extends TestCase
{
    public function testKeepsTheTargetAndReadSlotsDistinct(): void
    {
        $reference = new ColumnReference(1);
        $assignment = new UpdateAssignment(0, $reference);

        self::assertSame(0, $assignment->targetSlot);
        self::assertSame($reference, $assignment->value);
    }
}
