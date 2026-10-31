<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ScopedLocalAssignment;

#[CoversClass(ScopedLocalAssignment::class)]
#[Small]
final class ScopedLocalAssignmentTest extends TestCase
{
    public function testMessageDescribesTheBindingWithoutInputText(): void
    {
        self::assertSame('A local variable assignment cannot carry a system variable scope.', (new ScopedLocalAssignment(17))->message());
        self::assertNull((new ScopedLocalAssignment())->offset);
        self::assertSame(24, (new ScopedLocalAssignment(17, 24))->end);
        $this->expectExceptionMessage('The assigned-name position is nonnegative.');
        new ScopedLocalAssignment(-1);
    }

    public function testMessageRequiresAnOrderedAssignmentBoundary(): void
    {
        $this->expectExceptionMessage('An assignment completes after its assigned name.');
        new ScopedLocalAssignment(17, 12);
    }
}
