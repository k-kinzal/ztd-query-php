<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Identifier;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Identifier\Ascii;

#[CoversClass(Ascii::class)]
#[Small]
final class AsciiTest extends TestCase
{
    #[TestWith(['IDENTIFIER_012', 'identifier_012'])]
    #[TestWith(['IİıiÄä', 'iİıiÄä'])]
    #[TestWith(["\xDDI\0A\xFF", "\xDDi\0a\xFF"])]
    public function testLowerLeavesNonAsciiBytesUntouched(string $input, string $expected): void
    {
        self::assertSame($expected, Ascii::lower($input));
    }

    #[TestWith(['is distinct from', 'IS DISTINCT FROM'])]
    #[TestWith(['IİıiÄä', 'IİıIÄä'])]
    #[TestWith(["\xFDi\0z\xFF", "\xFDI\0Z\xFF"])]
    public function testUpperRecognizesAsciiKeywordsWithoutLocaleFolding(string $input, string $expected): void
    {
        self::assertSame($expected, Ascii::upper($input));
    }
}
