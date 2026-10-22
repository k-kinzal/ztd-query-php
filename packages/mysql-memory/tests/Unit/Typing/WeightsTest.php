<?php

declare(strict_types=1);

namespace Tests\Unit\Typing;

use MySqlMemory\Typing\Weights;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(Weights::class)]
#[Small]
final class WeightsTest extends TestCase
{
    public function testLegacyWidthDistinguishesWeightLimitsFromCharacterLimits(): void
    {
        $weights = new Weights();
        self::assertSame(1, $weights->legacyWidth(Collation::known('latin1_german2_ci')));
        self::assertSame(2, $weights->legacyWidth(Collation::known('utf8mb4_german2_ci')));
        self::assertSame(2, $weights->legacyWidth(Collation::known('utf8mb4_unicode_ci')));
        self::assertSame(2, $weights->legacyWidth(Collation::known('utf8mb4_unicode_520_ci')));
        self::assertNull($weights->legacyWidth(Collation::known('utf8mb4_general_ci')));
        self::assertNull($weights->legacyWidth(Collation::known('utf8mb4_0900_ai_ci')));
    }

    public function testTextPadsGeneralWeightsWithSpaces(): void
    {
        self::assertSame('0041004500200020', bin2hex((new Weights())->text('aÉ', Collation::known('utf8mb4_general_ci'), 4)));
    }

    public function testTextPadsBinaryWeightsOnlyWhenRequested(): void
    {
        self::assertSame('ab', (new Weights())->text('ab', Collation::binary(), 4));
        self::assertSame("ab\0\0", (new Weights())->text('ab', Collation::binary(), 4, 128));
    }

    public function testTextKeepsEmptyInputEmptyUnlessPaddingWasRequested(): void
    {
        self::assertSame('', (new Weights())->text('', Collation::known('utf8mb4_general_ci')));
        self::assertSame('00200020', bin2hex((new Weights())->text('', Collation::known('utf8mb4_general_ci'), 2)));
    }

    public function testTextWeighsUntailoredUnicode9CharactersSeparately(): void
    {
        self::assertSame('1d77028b', bin2hex((new Weights())->text('l·', Collation::known('utf8mb4_0900_ai_ci'))));
        self::assertSame('1caa1e711e71', bin2hex((new Weights())->text('éß', Collation::known('utf8mb4_0900_ai_ci'))));
    }

    public function testCharacterKeepsUtf8BinaryCodePointWidths(): void
    {
        self::assertSame('00e9', bin2hex((new Weights())->character('é', Collation::known('utf8mb3_bin'))));
        self::assertSame('0000e9', bin2hex((new Weights())->character('é', Collation::known('utf8mb4_bin'))));
    }

    public function testGeneralUsesSampledFoldingAndIdentityPages(): void
    {
        self::assertSame('0045', bin2hex((new Weights())->general(0xe9)));
        self::assertSame('4e00', bin2hex((new Weights())->general(0x4e00)));
        self::assertSame('fffd', bin2hex((new Weights())->general(0x1f600)));
    }
}
