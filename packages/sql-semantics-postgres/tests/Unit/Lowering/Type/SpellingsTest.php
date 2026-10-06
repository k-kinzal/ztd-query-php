<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Lowering\Type\Spellings;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\CharacterKeyword;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\DatetimeKeyword;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\ZoneOption;

#[CoversClass(Spellings::class)]
#[Small]
final class SpellingsTest extends TestCase
{
    public function testCharacterLowersTheSpellingVaryingAndLength(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT CAST(x AS national character varying (3))');
        $character = (new Spellings($lowering))->character($tree->find('Character')[0]);
        self::assertSame([CharacterKeyword::NationalCharacter, true, '3'], [$character->keyword, $character->varying, $character->length?->digits]);
    }

    public function testCharacterLowersASpellingWithoutLength(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse("SELECT varchar 'x'");
        $character = (new Spellings($lowering))->character($tree->find('ConstCharacter')[0]);
        self::assertSame([CharacterKeyword::Varchar, false, null], [$character->keyword, $character->varying, $character->length]);
    }

    public function testCharacterReportsAProductionThatIsNotACharacterType(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT CAST(x AS int)');
        $this->expectExceptionMessage('No semantic rule is implemented for: Numeric: INT_P');
        (new Spellings($lowering))->character($tree->find('Numeric')[0]);
    }

    public function testVaryingTellsWhetherTheKeywordIsWritten(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT CAST(x AS bit varying (3))');
        self::assertTrue((new Spellings($lowering))->varying($tree->find('opt_varying')[0]));
        self::assertFalse((new Spellings($lowering))->varying((new PostgreSqlParser('pg-17.2'))->parse('SELECT CAST(x AS bit)')->find('opt_varying')[0]));
    }

    public function testDatetimeLowersTheKeywordPrecisionAndZone(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT CAST(x AS time (3) with time zone)');
        $datetime = (new Spellings($lowering))->datetime($tree->find('ConstDatetime')[0]);
        self::assertSame([DatetimeKeyword::Time, '3', ZoneOption::WithTimeZone], [$datetime->keyword, $datetime->precision?->digits, $datetime->zone]);
    }

    public function testDatetimeLowersATimestampWithoutPrecision(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT CAST(x AS timestamp)');
        $datetime = (new Spellings($lowering))->datetime($tree->find('ConstDatetime')[0]);
        self::assertSame([DatetimeKeyword::Timestamp, null, null], [$datetime->keyword, $datetime->precision, $datetime->zone]);
    }

    public function testZoneLowersTheOptionOrNothing(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT CAST(x AS timestamp without time zone)');
        self::assertSame(ZoneOption::WithoutTimeZone, (new Spellings($lowering))->zone($tree->find('opt_timezone')[0]));
        self::assertNull((new Spellings($lowering))->zone((new PostgreSqlParser('pg-17.2'))->parse('SELECT CAST(x AS time)')->find('opt_timezone')[0]));
    }
}
