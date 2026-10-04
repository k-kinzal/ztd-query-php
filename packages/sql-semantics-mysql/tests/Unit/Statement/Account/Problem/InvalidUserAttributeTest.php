<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\InvalidUserAttribute;

#[CoversClass(InvalidUserAttribute::class)]
#[Small]
final class InvalidUserAttributeTest extends TestCase
{
    public function testMessageDescribesTheProblem(): void
    {
        self::assertSame('The user attribute x is not a JSON object (ER_INVALID_USER_ATTRIBUTE_JSON).', (new InvalidUserAttribute('x'))->message());
    }
}
