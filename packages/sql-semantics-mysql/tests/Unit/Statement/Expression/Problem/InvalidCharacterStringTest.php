<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\InvalidCharacterString;

#[CoversClass(InvalidCharacterString::class)]
#[Small]
final class InvalidCharacterStringTest extends TestCase
{
    public function testMessageRetainsCharacterBytesWithoutInputSyntax(): void
    {
        $problem = new InvalidCharacterString('utf32', "\0ab");

        self::assertSame(['Invalid utf32 character string.', '006162'], [$problem->message(), bin2hex($problem->bytes)]);
    }
}
