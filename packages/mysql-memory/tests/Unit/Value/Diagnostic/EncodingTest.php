<?php

declare(strict_types=1);

namespace Tests\Unit\Value\Diagnostic;

use MySqlMemory\Value\Diagnostic\Encoding;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;

#[CoversClass(Encoding::class)]
#[Small]
final class EncodingTest extends TestCase
{
    public function testPrefixPreservesModernCutBytesAndLegacyCompleteCharacters(): void
    {
        self::assertSame(str_repeat('a', 78) . "\xE7\x8C", Encoding::prefix(str_repeat('a', 78) . '猫', Charset::known('utf8mb4'), false));
        self::assertSame(str_repeat('a', 78), Encoding::prefix(str_repeat('a', 78) . '猫', Charset::known('utf8mb4'), true));
    }

    public function testPrefixConvertsToTheSystemCharsetBeforeBounding(): void
    {
        self::assertSame(str_repeat('a', 78) . '?t', Encoding::prefix(str_repeat('a', 78) . '😀tail', Charset::known('utf8mb4'), false));
        self::assertSame('é', Encoding::prefix("\xE9", Charset::known('latin1'), false));
        self::assertSame('\\xE7\\x8C\\xAB', Encoding::prefix('猫', Charset::known('binary'), false));
    }

    public function testRepairDistinguishesConditionsFromPackets(): void
    {
        self::assertSame('猫??tail', Encoding::repair("猫\xE7\x8Ctail"));
        self::assertSame("猫ç\u{008C}tail", Encoding::repair("猫\xE7\x8Ctail", true));
        self::assertSame('é猫😀', Encoding::repair('é猫😀'));
    }

    public function testPacketConvertsTheRepairedMessageToTheResultsEncoding(): void
    {
        self::assertSame('Ã', Encoding::packet("\xC3"));
        self::assertSame("\xC3", Encoding::packet("\xC3", 'latin1'));
        self::assertSame('\\732B', Encoding::packet('猫', 'latin1'));
        self::assertSame('\\00E9\\732B\\03A9\\00F0\\009F\\0098\\0080', Encoding::packet('é猫Ω😀', 'ascii'));
        self::assertSame('é猫Ω😀', Encoding::packet('é猫Ω😀', 'binary'));
    }

    public function testConditionConvertsSystemBytesWithoutPacketEscapes(): void
    {
        self::assertSame('é猫Ω????', Encoding::condition('é猫Ω😀', 'utf8mb4'));
        self::assertSame('???????', Encoding::condition('é猫Ω😀', 'ascii'));
        self::assertSame('é猫Ω😀', Encoding::condition('é猫Ω😀', 'binary'));
    }
    public function testPrefixEndsAtNulBeforeMessageFormatting(): void
    {
        self::assertSame('`zero', Encoding::prefix("`zero\0tail`", Charset::known('utf8mb4'), false));
        self::assertSame('`zero', Encoding::prefix("`zero\0tail`", Charset::known('utf8mb4'), true));
    }

}
