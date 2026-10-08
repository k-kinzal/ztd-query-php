<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Typing\Materialization;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\TypeFact;

#[CoversClass(Materialization::class)]
#[Small]
final class MaterializationTest extends TestCase
{
    public function testMergeableHoldsForAPlainQueryBlockOverTables(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $derived = static function (string $sql) use ($semantics): DerivedTable {
            $select = $semantics->analyze($sql)->statement;
            self::assertInstanceOf(Select::class, $select);
            self::assertInstanceOf(DerivedTable::class, $select->from);

            return $select->from;
        };

        self::assertTrue((new Materialization())->mergeable($derived('SELECT * FROM (SELECT a FROM t) d')->query));
        self::assertFalse((new Materialization())->mergeable($derived('SELECT * FROM (SELECT DISTINCT a FROM t) d')->query));
        self::assertFalse((new Materialization())->mergeable($derived('SELECT * FROM (SELECT COUNT(*) FROM t) d')->query));
        self::assertFalse((new Materialization())->mergeable($derived('SELECT * FROM (SELECT 1) d')->query));
    }

    public function testNarrowsForAQueryBlockOrASingleRow(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $query = static function (string $sql) use ($semantics): \SqlSemantics\Statement\Query {
            $select = $semantics->analyze($sql)->statement;
            self::assertInstanceOf(Select::class, $select);
            self::assertInstanceOf(DerivedTable::class, $select->from);

            return $select->from->query;
        };

        self::assertTrue((new Materialization())->narrows($query('SELECT * FROM (SELECT 1 LIMIT 1) d')));
        self::assertTrue((new Materialization())->narrows($query('SELECT * FROM (VALUES ROW(1)) d')));
        self::assertFalse((new Materialization())->narrows($query('SELECT * FROM (VALUES ROW(1), ROW(2)) d')));
        self::assertFalse((new Materialization())->narrows($query('SELECT * FROM (SELECT 1 UNION SELECT 2) d')));
    }

    public function testColumnNarrowsShortBigIntsAndDropsStringDecimals(): void
    {
        self::assertEquals(new Domain(Kind::Integer, Field::Long, 11, 0, false, null, [], Coercibility::Numeric, 2), (new Materialization())->column(Domain::integer(Field::LongLong, 2)));
        self::assertEquals(new Domain(Kind::Integer, Field::LongLong, 20, 0, false, null, [], Coercibility::Numeric, 2), (new Materialization())->column(Domain::integer(Field::LongLong, 2), false));
        self::assertSame(0, (new Materialization())->column(Domain::string(3, Collation::known('utf8mb4_bin')))->decimals);
    }

    public function testColumnNarrowsBelowTenCharactersAndKeepsACopiedColumn(): void
    {
        self::assertEquals(new Domain(Kind::Integer, Field::LongLong, 20, 0, false, null, [], Coercibility::Numeric, 10), (new Materialization())->column(Domain::integer(Field::LongLong, 10)));
        self::assertEquals(new Domain(Kind::Integer, Field::Long, 11, 0, false, null, [], Coercibility::Numeric, 9), (new Materialization())->column(Domain::integer(Field::LongLong, 9)));
        self::assertEquals(Domain::integer(Field::LongLong, 21, true), (new Materialization())->column(Domain::integer(Field::LongLong, 21, true)));
        self::assertEquals(Domain::column(Field::Long, 5), (new Materialization())->column(Domain::column(Field::Long, 5)));
    }

    public function testColumnLetsAnExpressionSeeTheWholeTypeOfADerivedColumn(): void
    {
        $output = (new Semantics(Dialect::MySql))->analyze('SELECT a, -a, a + 0 FROM (SELECT 3 AS a) d')->facts->output;

        self::assertNotNull($output);
        self::assertEquals([new Known(new Domain(Kind::Integer, Field::Long, 11, 0, false, null, [], Coercibility::Numeric, 2)), new Known(Domain::integer(Field::LongLong, 11)), new Known(Domain::integer(Field::LongLong, 12))], array_map(static fn (OutputSlot $slot): TypeFact => $slot->type, $output->shape->slots));
    }

    public function testSetCountsBlobLengthsInBytes(): void
    {
        self::assertSame(262140, (new Materialization())->set(Domain::string(65535, Collation::known('utf8mb4_bin'), Field::Blob))->length);
        self::assertSame(3, (new Materialization())->set(Domain::string(3, Collation::known('utf8mb4_bin')))->length);
        self::assertEquals(Domain::integer(), (new Materialization())->set(Domain::integer()));
    }

    public function testTextKeepsTheCollationWithoutDecimals(): void
    {
        self::assertEquals(new Domain(Kind::String, Field::VarString, 7, 0, false, Collation::known('latin1_bin')), (new Materialization())->text(Domain::string(3, Collation::known('latin1_bin')), 7));
    }

    public function testNothingMakesNullAnEmptyBinaryString(): void
    {
        self::assertEquals(new Domain(Kind::String, Field::VarString, 0, 0, false, Collation::binary(), [], Coercibility::Ignorable), (new Materialization())->nothing(Domain::null()));
        self::assertEquals(Domain::double(), (new Materialization())->nothing(Domain::double()));
    }

    public function testSetMakesANullColumnACharBeforeMySql81(): void
    {
        self::assertEquals(new Domain(Kind::String, Field::String, 0, 0, false, Collation::binary(), [], Coercibility::Ignorable), (new Materialization())->set(Domain::null(), GrammarRelease::MySql8044));
        self::assertSame(Field::VarString, (new Materialization())->set(Domain::null(), GrammarRelease::MySql910)->field);
    }

    public function testWindowedHoldsIntegersAsIntOrBigintAndStringsAsVarcharWithoutDecimals(): void
    {
        $materialization = new Materialization();
        $tiny = $materialization->windowed(Domain::column(Field::Tiny, 4));
        $int = $materialization->windowed(Domain::column(Field::Long, 11));
        $text = $materialization->windowed(Domain::string(3, Collation::known('utf8mb4_0900_ai_ci'), Field::String));
        $blob = $materialization->windowed(Domain::string(262140, Collation::known('utf8mb4_0900_ai_ci'), Field::Blob));
        $time = $materialization->windowed(new Domain(Kind::Time, Field::Time, 52, 2));

        self::assertSame([Field::Long, 4], [$tiny->field, $tiny->length]);
        self::assertSame([Field::LongLong, 11], [$int->field, $int->length]);
        self::assertSame([Field::VarString, 3, 0], [$text->field, $text->length, $text->decimals]);
        self::assertSame([Field::Blob, 1048560], [$blob->field, $blob->length]);
        self::assertSame([Field::Time, 13], [$time->field, $time->length]);
        self::assertSame([Field::VarString, 0], [$materialization->windowed(Domain::null())->field, $materialization->windowed(Domain::null())->length]);
    }
}
