<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Lexical;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Rules\Lexical\UnicodeEscapes;

#[CoversClass(UnicodeEscapes::class)]
#[Small]
final class UnicodeEscapesTest extends TestCase
{
    public function testDecodeReplacesFourAndSixDigitEscapes(): void
    {
        self::assertSame('aé😀\\', (new UnicodeEscapes())->decode('a\\00e9\\+01F600\\\\', '\\'));
    }

    public function testDecodeJoinsASurrogatePair(): void
    {
        self::assertSame('😀', (new UnicodeEscapes())->decode('!D83D!DE00', '!'));
    }

    public function testDecodeRejectsAMalformedEscape(): void
    {
        $this->expectException(AnalysisException::class);
        (new UnicodeEscapes())->decode('\\00G1', '\\');
    }

    public function testDecodeRejectsALoneSurrogate(): void
    {
        $this->expectException(AnalysisException::class);
        (new UnicodeEscapes())->decode('\\D83Dx', '\\');
    }

    public function testEncodeWritesUtf8(): void
    {
        self::assertSame('A', (new UnicodeEscapes())->encode(0x41));
        self::assertSame('é', (new UnicodeEscapes())->encode(0xE9));
        self::assertSame('猫', (new UnicodeEscapes())->encode(0x732B));
        self::assertSame('😀', (new UnicodeEscapes())->encode(0x1F600));
    }

    public function testEncodeRejectsCodePointZero(): void
    {
        $this->expectException(AnalysisException::class);
        (new UnicodeEscapes())->encode(0);
    }

    public function testRejectThrowsOnlyWhenTheConditionHolds(): void
    {
        (new UnicodeEscapes())->reject(false, 'never');
        $this->expectExceptionMessage('invalid Unicode escape');
        (new UnicodeEscapes())->reject(true, 'invalid Unicode escape');
    }
}
