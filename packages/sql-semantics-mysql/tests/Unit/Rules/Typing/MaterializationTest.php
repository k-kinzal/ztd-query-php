<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
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
        self::assertEquals(new Domain(Kind::Integer, Field::Long, 2, 0, false, null, [], Coercibility::Numeric), (new Materialization())->column(Domain::integer(Field::LongLong, 2)));
        self::assertEquals(Domain::integer(Field::LongLong, 2), (new Materialization())->column(Domain::integer(Field::LongLong, 2), false));
        self::assertSame(0, (new Materialization())->column(Domain::string(3, Collation::known('utf8mb4_bin')))->decimals);
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
}
