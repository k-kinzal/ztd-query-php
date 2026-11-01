<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidNamedConstant;

#[CoversClass(InvalidNamedConstant::class)]
#[Small]
final class InvalidNamedConstantTest extends TestCase
{
    public function testMessageDistinguishesANullNameFromAnInvalidArgument(): void
    {
        self::assertSame('Incorrect arguments to NAME_CONST', (new InvalidNamedConstant())->message());
        self::assertSame("The 'NAME_CONST' syntax is reserved for purposes internal to the MySQL server", (new InvalidNamedConstant(true))->message());
    }
}
