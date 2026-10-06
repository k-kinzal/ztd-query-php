<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\WrongArgumentCount;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(WrongArgumentCount::class)]
#[Small]
final class WrongArgumentCountTest extends TestCase
{
    public function testMessageDescribesTheProblem(): void
    {
        self::assertSame("Incorrect parameter count in the call to native function 'abs'", (new WrongArgumentCount(new Name('abs'), 2))->message());
    }
}
