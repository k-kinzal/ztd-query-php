<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Lowering\Type\Designations;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\BitDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\CharacterDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\DecimalDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\DecimalKeyword;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\FloatDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\KeywordDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\TypeKeyword;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Designations::class)]
#[Small]
final class DesignationsTest extends TestCase
{
    public function testSimpleLowersAKeywordSpelling(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT CAST(x AS double precision)');
        $designation = (new Designations($lowering))->simple($tree->find('SimpleTypename')[0]);
        self::assertInstanceOf(KeywordDesignation::class, $designation);
        self::assertSame(TypeKeyword::DoublePrecision, $designation->keyword);
    }

    public function testSimpleLowersACharacterSpelling(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT CAST(x AS char(2))');
        self::assertInstanceOf(CharacterDesignation::class, (new Designations($lowering))->simple($tree->find('SimpleTypename')[0]));
    }

    public function testConstantLowersTheTypeOfATypedConstant(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse("SELECT bit varying (3) '101'");
        $designation = (new Designations($lowering))->constant($tree->find('ConstTypename')[0]);
        self::assertInstanceOf(BitDesignation::class, $designation);
        self::assertTrue($designation->varying);
    }

    public function testConstantReportsAProductionThatIsNotAConstantType(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT CAST(x AS int)');
        $this->expectExceptionMessage('No semantic rule is implemented for: SimpleTypename: Numeric');
        (new Designations($lowering))->constant($tree->find('SimpleTypename')[0]);
    }

    public function testNamedLowersADottedTypeNameWithModifiers(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT CAST(x AS pg_catalog.Varchar(3))');
        $designation = (new Designations($lowering))->named($tree->find('GenericType')[0]);
        self::assertSame(['pg_catalog', 'varchar'], array_map(static fn (Name $part): string => $part->value, $designation->name->parts));
        self::assertCount(1, $designation->modifiers);
    }

    public function testModifiersIsEmptyWhenNoneAreWritten(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT CAST(x AS text)');
        self::assertSame([], (new Designations($lowering))->modifiers($tree->find('opt_type_modifiers')[0]));
    }

    public function testNumericLowersADecimalSpellingWithItsModifiers(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT CAST(x AS dec(5, 2))');
        $designation = (new Designations($lowering))->numeric($tree->find('Numeric')[0]);
        self::assertInstanceOf(DecimalDesignation::class, $designation);
        self::assertSame(DecimalKeyword::Dec, $designation->keyword);
        self::assertInstanceOf(Constant::class, $designation->modifiers[1]);
    }

    public function testNumericLowersAFloatWithItsPrecision(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT CAST(x AS float(10))');
        $designation = (new Designations($lowering))->numeric($tree->find('Numeric')[0]);
        self::assertInstanceOf(FloatDesignation::class, $designation);
        self::assertSame('10', $designation->precision?->digits);
    }

    public function testPrecisionIsNullWhenNoneIsWritten(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT CAST(x AS float)');
        self::assertNull((new Designations($lowering))->precision($tree->find('opt_float')[0]));
    }

    public function testPrecisionRejectsZeroBits(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT CAST(x AS float(0))');
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('precision for type float must be at least 1 bit');
        (new Designations($lowering))->precision($tree->find('opt_float')[0]);
    }

    public function testPrecisionRejectsMoreThanFiftyThreeBits(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT CAST(x AS float(54))');
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('precision for type float must be less than 54 bits');
        (new Designations($lowering))->precision($tree->find('opt_float')[0]);
    }

    public function testBitLowersTheVaryingFlagAndTheLengthModifiers(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT CAST(x AS bit varying (3))');
        $designation = (new Designations($lowering))->bit($tree->find('Bit')[0]);
        self::assertTrue($designation->varying);
        self::assertCount(1, $designation->modifiers);
        self::assertSame([], (new Designations($lowering))->bit((new PostgreSqlParser('pg-17.2'))->parse('SELECT CAST(x AS bit)')->find('Bit')[0])->modifiers);
    }
}
