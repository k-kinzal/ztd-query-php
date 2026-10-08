<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Pattern;

use MySqlMemory\Evaluation\Function\Pattern\Properties;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Properties::class)]
#[Small]
final class PropertiesTest extends TestCase
{
    public function testEscapeAnswersTheIcuClasses(): void
    {
        $properties = new Properties();

        self::assertSame(['[\p{Nd}]', '[^\t\n\f\r\p{Z}]', '[\t\p{Zs}]'], [$properties->escape('d')->source(), $properties->escape('S')->source(), $properties->escape('h')->source()]);
    }

    public function testMembersReadsANameOrAPropertyAndAValue(): void
    {
        $properties = new Properties();

        self::assertSame(['[\p{Lu}]', '[\p{Greek}]', null], [$properties->members('Uppercase Letter')?->source(), $properties->members('sc = Greek')?->source(), $properties->members('Foo')]);
    }

    public function testNamedKnowsAnyAsciiAssignedWordAndBlocks(): void
    {
        $properties = new Properties();

        self::assertSame(
            ['[\x{0}-\x{10FFFF}]', '[\x{0}-\x{7F}]', '[^\p{Cn}]', '[\x{0}-\x{7F}]', null],
            [$properties->named('Any')?->source(), $properties->named('ascii')?->source(), $properties->named('Assigned')?->source(), $properties->named('InBasic_Latin')?->source(), $properties->named('')],
        );
    }

    public function testValuedReadsBinaryAndEnumeratedProperties(): void
    {
        $properties = new Properties();
        $source = '/\A' . $properties->valued('Hex_Digit', 'no')?->source() . '\z/u';

        self::assertSame([0, 1, null, null], [preg_match($source, 'f'), preg_match($source, 'g'), $properties->valued('Hex_Digit', 'maybe'), $properties->valued('Foo', 'x')]);
    }

    public function testCategoryWritesTheShortName(): void
    {
        self::assertSame(['[\p{L&}]', null], [(new Properties())->category('Cased_Letter')?->source(), (new Properties())->category('Greek')]);
    }

    public function testScriptLeavesAKnownScriptToPcre(): void
    {
        self::assertSame(['[\p{Hiragana}]', null], [(new Properties())->script('Hira')?->source(), (new Properties())->script('Letter')]);
    }

    public function testCharacterAnswersTheCharacterOfAName(): void
    {
        self::assertSame(['[a]', null], [(new Properties())->character('latin small letter a')?->source(), (new Properties())->character('FOO')]);
    }

    public function testPointIgnoresTheCaseOfTheName(): void
    {
        self::assertSame(['1', null], [(new Properties())->point('Digit One'), (new Properties())->point('LATIN_SMALL_LETTER_A')]);
    }

    public function testAgeListsTheCharactersAssignedByAVersion(): void
    {
        $source = '/\A' . (new Properties())->age('1.1')?->source() . '\z/u';

        self::assertSame([1, 0, null], [preg_match($source, 'a'), preg_match($source, '€'), (new Properties())->age('x')]);
    }

    public function testListedListsTheCharactersThatPassATest(): void
    {
        self::assertSame('[\x{41}-\x{43}\x{45}]', (new Properties())->listed('test-listed', static fn (int $code): bool => in_array($code, [0x41, 0x42, 0x43, 0x45], true))->source());
    }
}
