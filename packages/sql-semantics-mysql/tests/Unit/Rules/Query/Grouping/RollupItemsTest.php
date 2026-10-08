<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Grouping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\Grouping\RollupItems;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field as FieldType;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(RollupItems::class)]
#[Medium]
final class RollupItemsTest extends TestCase
{
    public function testFieldsTypesTheRollupItemsAndKeepsTheOtherColumns(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b VARCHAR(10) NOT NULL, c INT NOT NULL, d DATE)');
        $operation = $semantics->analyze('SELECT b, d, c, IFNULL(c, 0), COUNT(*) FROM t GROUP BY b, d, c WITH ROLLUP', [$table]);
        $connection = Collation::known('utf8mb4_0900_ai_ci');

        self::assertSame([Nullability::Nullable, Nullability::Nullable, Nullability::Nullable, Nullability::Nullable, Nullability::NotNull], [$operation->field(0)->nullability, $operation->field(1)->nullability, $operation->field(2)->nullability, $operation->field(3)->nullability, $operation->field(4)->nullability]);
        self::assertEquals([new Known(new Domain(Kind::String, FieldType::VarString, 10, Domain::NOT_FIXED, false, $connection)), new Known(new Domain(Kind::Date, FieldType::Date, 10, 0, false, $connection))], [$operation->field(0)->type, $operation->field(1)->type]);
    }

    public function testFieldsKeepsTheNullFactOfAColumnThatIsNotGrouped(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b VARCHAR(10) NOT NULL)');
        $operation = $semantics->analyze('SELECT a, b FROM t GROUP BY a WITH ROLLUP', [$table]);

        self::assertSame([Nullability::Nullable, Nullability::NotNull], [$operation->field(0)->nullability, $operation->field(1)->nullability]);
    }

    public function testFieldsGivesTheRollupItemsTheColumnsOfATemporaryTableUnderOrderBy(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, f TINYINT UNSIGNED, d DATE)');
        $operation = $semantics->analyze('SELECT a, f, d FROM t GROUP BY a, f, d WITH ROLLUP ORDER BY a', [$table]);

        self::assertEquals([new Known(Domain::integer(FieldType::LongLong, 11)), new Known(Domain::integer(FieldType::Long, 3, true)), new Known(new Domain(Kind::Date, FieldType::Date, 10))], [$operation->field(0)->type, $operation->field(1)->type, $operation->field(2)->type]);
    }

    public function testRollsTellsWhetherABlockGroupsWithRollup(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $rollup = $semantics->analyze('SELECT a FROM t GROUP BY a WITH ROLLUP')->statement;
        $plain = $semantics->analyze('SELECT a FROM t GROUP BY a')->statement;

        self::assertInstanceOf(Select::class, $rollup);
        self::assertInstanceOf(Select::class, $plain);
        self::assertSame([true, false], [(new RollupItems())->rolls($rollup), (new RollupItems())->rolls($plain)]);
    }

    public function testGroupsAnswersTheSelectItemsAnAliasOrAPositionNames(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $operation = $semantics->analyze('SELECT a + 1 AS x, b FROM t GROUP BY x, 2 WITH ROLLUP', [$table]);
        $select = $operation->statement;

        self::assertInstanceOf(Select::class, $select);
        self::assertSame([$select->items[0]->expression ?? null, $select->items[1]->expression ?? null], (new RollupItems())->groups($select, $operation->facts));
    }

    public function testRolledTellsWhetherAFieldIsAGroupingExpression(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $operation = $semantics->analyze('SELECT t.*, a + 1 FROM t GROUP BY a, a + 1 WITH ROLLUP', [$table]);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $groups = (new RollupItems())->groups($select, $operation->facts);

        self::assertSame([true, false, true], [(new RollupItems())->rolled($operation->field(0), $groups, $operation->facts), (new RollupItems())->rolled($operation->field(1), $groups, $operation->facts), (new RollupItems())->rolled($operation->field(2), $groups, $operation->facts)]);
    }

    public function testReadsTellsWhetherAnExpressionReadsAGroupingExpressionOutsideAggregates(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $operation = $semantics->analyze('SELECT IFNULL(a, 0), SUM(a), GROUPING(a), b FROM t GROUP BY a WITH ROLLUP', [$table]);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $groups = (new RollupItems())->groups($select, $operation->facts);
        $items = array_values(array_filter($select->items, static fn (object $item): bool => $item instanceof SelectExpression));
        self::assertContainsOnlyInstancesOf(SelectExpression::class, $items);

        self::assertSame([true, false, false, false], array_map(static fn (SelectExpression $item): bool => (new RollupItems())->reads($item->expression, $groups, $operation->facts), $items));
    }

    public function testBeforeAnswersTheNullFactOfAColumnBeforeGrouping(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT NOT NULL)');
        $operation = $semantics->analyze('SELECT b, b + 1 FROM t GROUP BY a WITH ROLLUP', [$table]);

        self::assertSame([Nullability::NotNull, Nullability::Nullable], [$operation->field(0)->nullability, $operation->field(1)->nullability]);
    }

    public function testItemAnswersTheTypeOfARollupItemAsTheBlockAnswersIt(): void
    {
        $connection = Collation::known('utf8mb4_0900_ai_ci');
        $items = new RollupItems();

        self::assertEquals(
            [
                new Domain(Kind::String, FieldType::Blob, 262140, Domain::NOT_FIXED, false, $connection),
                new Domain(Kind::Date, FieldType::Date, 10, 0, false, $connection),
                new Domain(Kind::Json, FieldType::Json, 1073741823, Domain::NOT_FIXED, false, $connection),
                Domain::integer(FieldType::Long, 11),
            ],
            [
                $items->item(new Domain(Kind::String, FieldType::Blob, 65535, 0, false, $connection), $connection),
                $items->item(new Domain(Kind::Date, FieldType::Date, 10), $connection),
                $items->item(new Domain(Kind::Json, FieldType::Json, 4294967295), $connection),
                $items->item(Domain::integer(FieldType::Long, 11), $connection),
            ],
        );
    }

    public function testMaterializedAnswersTheColumnOfATemporaryTable(): void
    {
        $connection = Collation::known('utf8mb4_0900_ai_ci');
        $items = new RollupItems();

        self::assertEquals(
            [
                Domain::integer(FieldType::LongLong, 11),
                Domain::integer(FieldType::Long, 3, true),
                new Domain(Kind::String, FieldType::VarString, 255, 0, false, $connection),
                new Domain(Kind::String, FieldType::Blob, 262140, 0, false, $connection),
                new Domain(Kind::String, FieldType::VarString, 1, 0, false, $connection),
                new Domain(Kind::Date, FieldType::Date, 10),
                new Domain(Kind::Json, FieldType::Json, 4294967295),
            ],
            [
                $items->materialized(Domain::integer(FieldType::Long, 11)),
                $items->materialized(new Domain(Kind::Bit, FieldType::Bit, 3)),
                $items->materialized(new Domain(Kind::String, FieldType::Blob, 1020, Domain::NOT_FIXED, false, $connection)),
                $items->materialized(new Domain(Kind::String, FieldType::Blob, 262140, Domain::NOT_FIXED, false, $connection)),
                $items->materialized(new Domain(Kind::String, FieldType::Enum, 1, Domain::NOT_FIXED, false, $connection, ['p'])),
                $items->materialized(new Domain(Kind::Date, FieldType::Date, 10, 0, false, $connection)),
                $items->materialized(new Domain(Kind::Json, FieldType::Json, 1073741823, Domain::NOT_FIXED, false, $connection)),
            ],
        );
    }

    public function testRolledAtTellsWhetherAMaterializedColumnIsARollupItem(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $query = $semantics->analyze('SELECT a, COUNT(*) FROM t GROUP BY a WITH ROLLUP', [$table])->statement;
        self::assertInstanceOf(Select::class, $query);
        $derivation = new Derivation($semantics->context([$table]));
        $query->deriveStatement($derivation);

        self::assertSame([true, false], [(new RollupItems())->rolledAt($query, 0, $derivation), (new RollupItems())->rolledAt($query, 1, $derivation)]);
    }

    public function testWindowedGivesTheRollupItemsTheColumnsOfTheTemporaryTableOfTheWindow(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (p INT)');
        $operation = $semantics->analyze('SELECT p, ROW_NUMBER() OVER (ORDER BY p DESC) FROM t GROUP BY p WITH ROLLUP', [$table]);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);

        self::assertTrue((new RollupItems())->windowed($select));
        self::assertEquals(new Known(Domain::integer(FieldType::LongLong, 11)), $operation->field(0)->type);
        self::assertSame(Nullability::NotNull, $operation->field(1)->nullability);
    }
}
