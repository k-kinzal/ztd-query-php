<?php

declare(strict_types=1);

namespace Tests\Unit\Typing;

use Collator;
use MySqlMemory\Typing\Ordering;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(Ordering::class)]
#[Small]
final class OrderingTest extends TestCase
{
    public function testOfSharesTheOrderingOfACollation(): void
    {
        $ordering = Ordering::of(Collation::known('utf8mb4_0900_as_cs'));

        self::assertSame([$ordering, 'utf8mb4_0900_as_cs'], [Ordering::of(Collation::known('utf8mb4_0900_as_cs')), $ordering->collation->name]);
    }

    public function testCollatorWeighsEachUnicodeCollationAtItsStrength(): void
    {
        self::assertSame([Collator::PRIMARY, Collator::SECONDARY, Collator::TERTIARY, Collator::PRIMARY], [Ordering::collator(Collation::known('utf8mb4_0900_ai_ci'))?->getStrength(), Ordering::collator(Collation::known('utf8mb4_0900_as_ci'))?->getStrength(), Ordering::collator(Collation::known('utf8mb4_0900_as_cs'))?->getStrength(), Ordering::collator(Collation::known('utf8mb4_unicode_ci'))?->getStrength()]);
    }

    public function testCollatorIsNullForAByteOrFoldingCollation(): void
    {
        self::assertSame([null, null, null], [Ordering::collator(Collation::known('utf8mb4_bin')), Ordering::collator(Collation::known('utf8mb4_general_ci')), Ordering::collator(Collation::binary())]);
    }

    public function testCompareIgnoresCaseAndAccentsInAnAccentInsensitiveCollation(): void
    {
        $ordering = Ordering::of(Collation::known('utf8mb4_0900_ai_ci'));

        self::assertSame([0, 0, -1, 1], [$ordering->compare('a', 'A'), $ordering->compare('é', 'E'), $ordering->compare('a', 'b'), $ordering->compare('a ', 'a')]);
    }

    public function testCompareTellsCaseAndAccentsApartInACaseSensitiveCollation(): void
    {
        $ordering = Ordering::of(Collation::known('utf8mb4_0900_as_cs'));

        self::assertSame([-1, -1], [$ordering->compare('a', 'A'), $ordering->compare('e', 'é')]);
    }

    public function testCompareUsesTheBytesOfABinaryCollation(): void
    {
        $ordering = Ordering::of(Collation::known('utf8mb4_bin'));

        self::assertSame([1, 0], [$ordering->compare('a', 'B'), $ordering->compare('a ', 'a')]);
    }

    public function testCompareFoldsCaseAndAccentsOfAGeneralCollation(): void
    {
        $general = Ordering::of(Collation::known('utf8mb4_general_ci'));
        $latin = Ordering::of(Collation::known('latin1_swedish_ci'));

        self::assertSame([0, 0, 0, -1], [$general->compare('é', 'E'), $general->compare('a  ', 'A'), $latin->compare('abc', 'ABC'), $latin->compare('a', 'b')]);
    }

    public function testKeyIsEqualExactlyForStringsThatCompareEqual(): void
    {
        $ordering = Ordering::of(Collation::known('utf8mb4_0900_ai_ci'));

        self::assertSame([true, false, false], [$ordering->key('a') === $ordering->key('Á'), $ordering->key('a') === $ordering->key('b'), $ordering->key('a') === $ordering->key('a ')]);
    }

    public function testKeyIsTheFoldedTextOfAGeneralCollation(): void
    {
        $general = Ordering::of(Collation::known('utf8mb4_general_ci'));
        $binary = Ordering::of(Collation::known('utf8mb4_bin'));

        self::assertSame(['E', 'a'], [$general->key('é '), $binary->key('a ')]);
    }

    public function testPaddedRemovesTrailingSpacesOfAPadSpaceCollation(): void
    {
        self::assertSame(['a', 'a  '], [Ordering::of(Collation::known('utf8mb4_bin'))->padded('a  '), Ordering::of(Collation::known('utf8mb4_0900_bin'))->padded('a  ')]);
    }

    public function testFoldedUppercasesAndStripsAccents(): void
    {
        self::assertSame(['EA', 'AB'], [Ordering::of(Collation::known('utf8mb4_general_ci'))->folded('éa'), Ordering::of(Collation::known('latin1_swedish_ci'))->folded('ab')]);
    }

    public function testUnicodeReadsAWideCharacterSetInUtf8(): void
    {
        self::assertSame(['é', "\xE9", "\x00\xE9"], [Ordering::of(Collation::known('ucs2_general_ci'))->unicode("\x00\xE9"), Ordering::of(Collation::known('latin1_swedish_ci'))->unicode("\xE9"), Ordering::of(Collation::known('ucs2_bin'))->unicode("\x00\xE9")]);
    }

    public function testCompareComparesAWideCharacterSetByItsCharacters(): void
    {
        self::assertSame([0, -1], [Ordering::of(Collation::known('utf16_unicode_ci'))->compare("\x00\xE9", "\x00\xC9"), Ordering::of(Collation::known('utf16_unicode_ci'))->compare("\x00a", "\x00b")]);
    }

    public function testWeightsWeighEachLatin1ByteAsTheServerDoes(): void
    {
        $swedish = Ordering::weights()['latin1_swedish_ci'];

        self::assertSame(256, strlen($swedish));
        self::assertSame('A', $swedish[0xC3]);
        self::assertSame(-1, Ordering::of(Collation::known('latin1_swedish_ci'))->compare("\xC3\x89clair", 'Banana'));
    }

    public function testBytesAnswersEveryByteInOrder(): void
    {
        self::assertSame([256, "\x00", "\xFF"], [strlen(Ordering::bytes()), Ordering::bytes()[0], Ordering::bytes()[255]]);
    }

    public function testCompareComparesNumericStringsByTheirBytes(): void
    {
        self::assertSame([-1, -1], [Ordering::of(Collation::known('utf8mb4_bin'))->compare('10', '9'), Ordering::of(Collation::binary())->compare('10', '9')]);
    }
}
