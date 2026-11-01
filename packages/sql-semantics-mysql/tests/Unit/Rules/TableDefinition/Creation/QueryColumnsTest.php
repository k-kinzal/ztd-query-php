<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition\Creation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Creation\QueryColumns;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(QueryColumns::class)]
#[Small]
final class QueryColumnsTest extends TestCase
{
    public function testColumnRetainsDecodedNamesCollationAndDefaultValues(): void
    {
        $name = new Name('a`b\\c');
        $column = (new QueryColumns(GrammarRelease::MySql847))->column($name, Domain::string(7, Collation::known('latin1_german2_ci')), false);
        self::assertSame($name, $column->name->column);
        self::assertEquals(new M\Table\Column\OrdinaryColumn(new M\Type\Character(M\Type\Kind\CharacterKind::VarChar, '7', charset: new M\Type\CharsetAttribute(M\Type\Kind\CharsetForm::CharacterSet, new Name('latin1'))), [
            new M\Table\Column\CollateAttribute(new M\Name\CollationName(new Name('latin1_german2_ci'))),
            new M\Table\Column\KeywordAttribute(M\Table\Column\Kind\ColumnKeyword::NotNull),
            new M\Table\Column\DefaultLiteral(new M\Literal\StringLiteral([''])),
        ]), $column->specification);
        self::assertSame([], (new QueryColumns(GrammarRelease::MySql847))->column($name, Domain::null(), false)->specification->columnAttributes());
    }

    public function testFieldRetainsStorageRolesAndYearIntegerStorage(): void
    {
        $rules = new QueryColumns(GrammarRelease::MySql8044);
        self::assertSame(Field::LongLong, $rules->field(new Domain(Kind::Year, Field::VarString, 4)));
        self::assertSame(Field::Long, $rules->field(Domain::integer(Field::Tiny, 4)->withStorageField(Field::Long)));
        self::assertSame(Field::Year, $rules->field(new Domain(Kind::Year, Field::Year, 4)));
    }

    public function testWidthPreservesLiteralAndCopiedWidthsIndependentlyOfComputedFields(): void
    {
        $rules = new QueryColumns(GrammarRelease::MySql847);
        $literal = new M\Literal\NumberLiteral('123456789012345678901234567890e0');
        self::assertSame(32, $rules->width(Domain::double(32), $literal));
        self::assertSame(23, $rules->width(Domain::double(23), null));
        self::assertSame(3, $rules->width(Domain::double(3), new M\Expression\Grouped(new M\Expression\Operator\Unary(M\Expression\Operator\UnaryOperator::Plus, new M\Literal\NumberLiteral('1e2')))));
        self::assertSame(22, $rules->width(Domain::double(23), new M\Expression\Operator\Unary(M\Expression\Operator\UnaryOperator::Minus, new M\Literal\NumberLiteral('1e2'))));
        self::assertSame(23, $rules->width(Domain::double(23), new M\Name\ColumnUse(new Name('v'))));
        self::assertSame(32, $rules->width(Domain::double(32), null, true));
        self::assertSame(23, (new QueryColumns(GrammarRelease::MySql5744))->width(Domain::double(23), null));
    }

    /**
     * @return iterable<string, array{Domain, M\Type\DeclaredName}>
     */
    public static function providerTypes(): iterable
    {
        yield 'decimal' => [Domain::decimal(7, 2, true), new M\Type\Decimal('7', '2', [M\Type\Kind\NumericModifier::Unsigned])];
        yield 'null' => [Domain::null(), new M\Type\Binary(M\Type\Kind\BinaryKind::VarBinary, '0')];
        yield 'date' => [new Domain(Kind::Date, Field::Date, 10), new M\Type\Temporal(M\Type\Kind\TemporalKind::Date)];
        yield 'time' => [new Domain(Kind::Time, Field::Time, 14, 3), new M\Type\Temporal(M\Type\Kind\TemporalKind::Time, '3')];
        yield 'datetime' => [new Domain(Kind::DateTime, Field::DateTime, 19), new M\Type\Temporal(M\Type\Kind\TemporalKind::DateTime)];
        yield 'timestamp' => [new Domain(Kind::DateTime, Field::Timestamp, 26, 6), new M\Type\Temporal(M\Type\Kind\TemporalKind::Timestamp, '6')];
        yield 'year' => [new Domain(Kind::Year, Field::Year, 4), new M\Type\Temporal(M\Type\Kind\TemporalKind::Year)];
        yield 'bit' => [new Domain(Kind::Bit, Field::Bit, 5), new M\Type\Elementary(M\Type\Kind\ElementaryKind::Bit, '5')];
        yield 'json' => [new Domain(Kind::Json, Field::Json), new M\Type\Elementary(M\Type\Kind\ElementaryKind::Json)];
        yield 'vector' => [new Domain(Kind::String, Field::Vector, 12), new M\Type\Elementary(M\Type\Kind\ElementaryKind::Vector, '3')];
        yield 'geometry' => [new Domain(Kind::String, Field::Geometry), new M\Type\Spatial(M\Type\Kind\SpatialKind::Geometry)];
    }

    #[DataProvider('providerTypes')]
    public function testTypePreservesCompleteDimensions(Domain $domain, M\Type\DeclaredName $expected): void
    {
        self::assertEquals($expected, (new QueryColumns(GrammarRelease::MySql847))->type($domain));
    }

    public function testTypeRetainsLegacyNullStorage(): void
    {
        self::assertEquals(new M\Type\Binary(M\Type\Kind\BinaryKind::Binary, '0'), (new QueryColumns(GrammarRelease::MySql8044))->type(Domain::null()));
    }

    public function testFloatingRetainsFixedScalesAndReleaseSpecificWidths(): void
    {
        $domain = new Domain(Kind::Double, Field::Double, 9, 3, true);
        self::assertEquals(new M\Type\Floating(M\Type\Kind\FloatingKind::Double, '22', '3', [M\Type\Kind\NumericModifier::Unsigned]), (new QueryColumns(GrammarRelease::MySql847))->floating($domain));
        self::assertSame('9', (new QueryColumns(GrammarRelease::MySql5651))->floating($domain)->precision);
        self::assertNull((new QueryColumns(GrammarRelease::MySql847))->floating(Domain::double())->precision);
        self::assertSame('8', (new QueryColumns(GrammarRelease::MySql847))->floating(new Domain(Kind::Double, Field::Float, 8, 2))->precision);
    }

    public function testIntegerDistinguishesValueBoundsAndDisplayWidths(): void
    {
        $rules = new QueryColumns(GrammarRelease::MySql847);
        self::assertSame(M\Type\Kind\IntegralKind::Int, $rules->integer(Domain::integer(Field::LongLong, 10))->kind);
        self::assertSame(M\Type\Kind\IntegralKind::BigInt, (new QueryColumns(GrammarRelease::MySql5744))->integer(Domain::integer(Field::LongLong, 10))->kind);
        self::assertEquals(new M\Type\Integral(M\Type\Kind\IntegralKind::TinyInt, '2', [M\Type\Kind\NumericModifier::Unsigned]), $rules->integer(new Domain(Kind::Integer, Field::Tiny, 4, unsigned: true, display: 2)));
    }

    public function testCharacterRetainsMembersWithoutQuotingAndSizesByBytes(): void
    {
        $rules = new QueryColumns(GrammarRelease::MySql847);
        $members = ['a\\b', "c'd"];
        $type = $rules->character(new Domain(Kind::String, Field::Enum, 3, collation: Collation::known('latin1_bin'), members: $members));
        self::assertInstanceOf(M\Type\Enumeration::class, $type);
        self::assertSame($members, array_map(static fn (M\Literal\Text $text): string => $text->bytes(), $type->members));
        self::assertSame('VARCHAR', $rules->character(Domain::string(512, Collation::known('utf8mb4_bin')))->name());
        self::assertSame('TEXT', $rules->character(Domain::string(513, Collation::known('utf8mb4_bin')))->name());
        self::assertSame('MEDIUMTEXT', $rules->character(new Domain(Kind::String, Field::Blob, 65536, collation: Collation::known('latin1_bin')))->name());
        self::assertSame('TINYBLOB', $rules->character(new Domain(Kind::String, Field::TinyBlob, 255))->name());
        self::assertSame('LONGBLOB', $rules->character(new Domain(Kind::String, Field::LongBlob, 16777216))->name());
    }

    public function testDefaultDistinguishesImplicitValuesAndAbsentDefaults(): void
    {
        $rules = new QueryColumns(GrammarRelease::MySql847);
        self::assertSame('0.00', $rules->default(Domain::decimal(7, 2)));
        self::assertSame('00:00:00.000', $rules->default(new Domain(Kind::Time, Field::Time, 14, 3)));
        self::assertNull($rules->default(new Domain(Kind::DateTime, Field::DateTime, 19)));
        self::assertNull($rules->default(new Domain(Kind::String, Field::LongBlob, 16777216)));
    }

    public function testDefaultRetainsMySql56ComputedTemporalZeroValues(): void
    {
        $rules = new QueryColumns(GrammarRelease::MySql5651);
        self::assertSame('0000-00-00', $rules->default(new Domain(Kind::Date, Field::Date, 10)));
        self::assertSame('0000-00-00 00:00:00.000', $rules->default(new Domain(Kind::DateTime, Field::DateTime, 23, 3)));
        self::assertNull((new QueryColumns(GrammarRelease::MySql5744))->default(new Domain(Kind::DateTime, Field::DateTime, 23, 3)));
        self::assertSame('0000-00-00', (new QueryColumns(GrammarRelease::MySql847, false))->default(new Domain(Kind::Date, Field::Date, 10)));
    }
}
