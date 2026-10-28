<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Routine\Declaration\ReturnCollation;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\CharsetAttribute;
use SqlSemantics\Platform\MySql\Statement\Type\Enumeration;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryMark;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(ReturnCollation::class)]
#[Medium]
final class ReturnCollationTest extends TestCase
{
    public function testIntrinsicDistinguishesBareCharacterBinaryFromEnumAndNationalTypes(): void
    {
        $rule = new ReturnCollation();
        $attribute = new CharsetAttribute(CharsetForm::Binary);
        self::assertTrue($rule->intrinsic(new Character(CharacterKind::Char, '1', charset: $attribute)));
        self::assertFalse($rule->intrinsic(new Character(CharacterKind::Char, '1', true, $attribute)));
        self::assertFalse($rule->intrinsic(new Enumeration(\SqlSemantics\Platform\MySql\Statement\Type\Kind\EnumerationKind::Enum, [new \SqlSemantics\Platform\MySql\Statement\Literal\Text('a')], $attribute)));
        self::assertFalse($rule->intrinsic(new Character(CharacterKind::TinyText, charset: $attribute)));
    }

    /**
     * @return iterable<string, array{GrammarRelease, bool}>
     */
    public static function providerReleases(): iterable
    {
        yield '5.6' => [GrammarRelease::MySql5651, true];
        yield '5.7' => [GrammarRelease::MySql5744, true];
        yield '8.4' => [GrammarRelease::MySql847, false];
    }

    #[DataProvider('providerReleases')]
    public function testProblemRetainsNamedCharacterBinaryOnlyOnLegacyReleases(GrammarRelease $release, bool $refused): void
    {
        $rule = new ReturnCollation();
        $type = new Character(CharacterKind::Char, '1', charset: new CharsetAttribute(CharsetForm::Named, new Name('latin1'), BinaryMark::Leading));
        $ascii = new Character(CharacterKind::Char, '1', charset: new CharsetAttribute(CharsetForm::Ascii, mark: BinaryMark::Trailing));
        self::assertSame($refused, $rule->problem($type, $release) !== null);
        self::assertNull($rule->problem($ascii, $release));
        self::assertNull($rule->problem(new Character(CharacterKind::Text, charset: new CharsetAttribute(CharsetForm::Binary)), $release));
        self::assertNull($rule->problem(new Character(CharacterKind::LongVarChar, charset: new CharsetAttribute(CharsetForm::Named, new Name('latin1'), BinaryMark::Leading)), $release));
    }

    #[DataProvider('providerReleases')]
    public function testResolveDistinguishesStoredEnumerationAttributes(GrammarRelease $release, bool $legacy): void
    {
        $rule = new ReturnCollation();
        $default = \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known('utf8mb4_0900_ai_ci');
        self::assertSame($default, $rule->resolve(new Character(CharacterKind::Text, charset: new CharsetAttribute(CharsetForm::Binary)), null, $default, $release));
        $bare = new Enumeration(\SqlSemantics\Platform\MySql\Statement\Type\Kind\EnumerationKind::Enum, [new \SqlSemantics\Platform\MySql\Statement\Literal\Text('a')], new CharsetAttribute(CharsetForm::Binary));
        $named = new Enumeration(\SqlSemantics\Platform\MySql\Statement\Type\Kind\EnumerationKind::Enum, $bare->members, new CharsetAttribute(CharsetForm::Named, new Name('latin1'), BinaryMark::Trailing));

        self::assertSame($default, $rule->resolve($bare, null, $default, $release));
        self::assertSame($legacy ? 'latin1_swedish_ci' : 'latin1_bin', $rule->resolve($named, null, $default, $release)->name);
        self::assertSame('latin1_bin', $rule->resolve($named, new \SqlSemantics\Platform\MySql\Statement\Name\CollationName(new Name('latin1_bin')), $default, $release)->name);
    }
}
