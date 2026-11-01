<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\InvalidColumnAttribute;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(InvalidColumnAttribute::class)]
#[Small]
final class InvalidColumnAttributeTest extends TestCase
{
    public function testMessageDescribesTheAttributeAndColumn(): void
    {
        self::assertSame("Invalid default value for 'c'", (new InvalidColumnAttribute(new Name('c'), 'DEFAULT'))->message());
        self::assertSame("Invalid ON UPDATE clause for 'c' column", (new InvalidColumnAttribute(new Name('c'), 'ON UPDATE'))->message());
        self::assertSame('Incorrect usage of SRID and non-geometry column', (new InvalidColumnAttribute(new Name('c'), 'SRID'))->message());
    }
}
