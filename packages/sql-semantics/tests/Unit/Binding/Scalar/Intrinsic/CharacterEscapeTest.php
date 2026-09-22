<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Intrinsic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binding\Scalar\Intrinsic\CharacterEscape;

#[CoversClass(CharacterEscape::class)]
final class CharacterEscapeTest extends TestCase
{
    #[TestWith(['y\\x65ar', 'year'])]
    #[TestWith(['y\\145ar', 'year'])]
    #[TestWith(['y\\u0065ar', 'year'])]
    #[TestWith(['y\\U00000065ar', 'year'])]
    #[TestWith(['y\\ear', 'year'])]
    #[TestWith(['y\\nar', "y\nar"])]
    public function testPostgresDecodesCharacterEscapes(string $input, string $expected): void
    {
        self::assertSame($expected, CharacterEscape::postgres($input));
    }

    #[TestWith([101, 'e'])]
    #[TestWith([233, 'é'])]
    #[TestWith([24180, '年'])]
    #[TestWith([128197, '📅'])]
    public function testUnicodeEncodesCodePoints(int $point, string $expected): void
    {
        self::assertSame($expected, CharacterEscape::unicode($point));
    }
}
