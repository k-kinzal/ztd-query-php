<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Name\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Name\Problem\NameTooLong;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(NameTooLong::class)]
#[Small]
final class NameTooLongTest extends TestCase
{
    public function testMessagePreservesTheDecodedIdentifier(): void
    {
        $name = new Name(str_repeat('p', 65));
        self::assertSame("Identifier name '" . $name->value . "' is too long", (new NameTooLong($name))->message());
    }
}
