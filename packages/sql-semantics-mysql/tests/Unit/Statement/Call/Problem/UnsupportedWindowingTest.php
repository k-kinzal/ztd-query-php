<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\UnsupportedWindowing;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowingLimit;

#[CoversClass(UnsupportedWindowing::class)]
#[Small]
final class UnsupportedWindowingTest extends TestCase
{
    public function testMessageDescribesTheProblem(): void
    {
        self::assertSame("This version of MySQL doesn't yet support 'EXCLUDE'", (new UnsupportedWindowing(WindowingLimit::Exclusion))->message());
    }
}
