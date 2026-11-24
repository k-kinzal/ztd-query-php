<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Trigger\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerAssignment;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerTargetField;

#[CoversClass(TriggerAssignment::class)]
#[Small]
final class TriggerAssignmentTest extends TestCase
{
    public function testKeepsTheWriteAndOldTargetReadAsDistinctRoles(): void
    {
        $value = new TriggerTargetField(0);
        $assignment = new TriggerAssignment(2, $value);

        self::assertSame(2, $assignment->column);
        self::assertSame($value, $assignment->value);
    }
}
