<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidWindowCount;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind;

#[CoversClass(InvalidWindowCount::class)]
#[Small]
final class InvalidWindowCountTest extends TestCase
{
    public function testMessageIdentifiesTheCountOwningFunction(): void
    {
        self::assertSame('Incorrect arguments to nth_value', (new InvalidWindowCount(WindowFunctionKind::NthValue))->message());
    }

    public function testValueRefusesAFunctionWithoutACount(): void
    {
        $this->expectExceptionMessage('A count condition belongs to a function with a count or offset.');
        new InvalidWindowCount(WindowFunctionKind::Rank);
    }
}
