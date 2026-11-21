<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\NamedArgument;

#[CoversClass(NamedArgument::class)]
#[Small]
final class NamedArgumentTest extends TestCase
{
    public function testMessageDescribesTheProblem(): void
    {
        self::assertSame("Incorrect parameters in the call to native function 'abs'", (new NamedArgument(new Name('abs')))->message());
    }
}
