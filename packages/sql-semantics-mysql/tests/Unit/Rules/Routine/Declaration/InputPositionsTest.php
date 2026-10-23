<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Routine\Declaration\InputPositions;

#[CoversClass(InputPositions::class)]
#[Small]
final class InputPositionsTest extends TestCase
{
    public function testCheckedPreservesNumericCompletionBoundaries(): void
    {
        self::assertSame([0, 23, 42], (new InputPositions())->checked([0, 23, 42]));
    }
}
