<?php

declare(strict_types=1);

namespace Tests\Unit\Plan;

use MySqlMemory\Instance;
use MySqlMemory\Plan\SortedDistinct;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(SortedDistinct::class)]
#[Small]
final class SortedDistinctTest extends TestCase
{
    public function testApplyStoresOnlyTheSelectedBitSortColumnAsAnInteger(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(a BIT(8),b BIT(64)); INSERT INTO t VALUES(65,18446744073709551615),(0,0),(65,18446744073709551615)');
        $result = $session->query('SELECT DISTINCT a,b FROM t ORDER BY (a)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([Field::Long, Field::Bit], array_map(static fn ($column): Field => $column->type, $result->columns));
        self::assertSame([['0', str_repeat("\0", 8)], ['65', str_repeat("\xff", 8)]], $result->rows);
    }

    public function testIndexedNeedsAnOrderedStructureToAvoidDuplicateStorage(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(a BIT(8),KEY k(a)) ENGINE=MEMORY');
        $hash = $session->query('SELECT DISTINCT a FROM t ORDER BY a')[0];
        $session->query('ALTER TABLE t DROP INDEX k,ADD KEY k(a) USING BTREE');
        $btree = $session->query('SELECT DISTINCT a FROM t ORDER BY a')[0];
        self::assertInstanceOf(ResultSet::class, $hash);
        self::assertInstanceOf(ResultSet::class, $btree);

        self::assertSame([Field::Long, Field::Bit], [$hash->columns[0]->type, $btree->columns[0]->type]);
    }

    public function testAvailableUsesInvisibleIndexesOnlyWhenTheSessionEnablesThem(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(a BIT(8),KEY k(a) INVISIBLE)');
        $hidden = $session->query('SELECT DISTINCT a FROM t ORDER BY a')[0];
        $session->query("SET optimizer_switch='use_invisible_indexes=on'");
        $enabled = $session->query('SELECT DISTINCT a FROM t ORDER BY a')[0];
        self::assertInstanceOf(ResultSet::class, $hidden);
        self::assertInstanceOf(ResultSet::class, $enabled);

        self::assertSame([Field::Long, Field::Bit], [$hidden->columns[0]->type, $enabled->columns[0]->type]);
    }

    /**
     * @return iterable<string, array{string, string, Field}>
     */
    public static function providerIndexes(): iterable
    {
        yield 'ordinary column' => ['', 'SELECT DISTINCT a FROM t ORDER BY a', Field::Long];
        yield 'primary' => [',PRIMARY KEY(a)', 'SELECT DISTINCT a,b FROM t ORDER BY a', Field::Bit];
        yield 'nullable unique covered' => [',UNIQUE(a)', 'SELECT DISTINCT a FROM t ORDER BY a', Field::Bit];
        yield 'nullable unique uncovered' => [',UNIQUE(a)', 'SELECT DISTINCT a,b FROM t ORDER BY a', Field::Long];
        yield 'ordinary covered' => [',KEY(a)', 'SELECT DISTINCT a FROM t z ORDER BY a', Field::Bit];
        yield 'ordinary uncovered' => [',KEY(a)', 'SELECT DISTINCT a,b FROM t ORDER BY a', Field::Long];
        yield 'composite covered' => [',KEY(a,b)', 'SELECT DISTINCT a,b FROM t ORDER BY a', Field::Bit];
        yield 'empty output' => ['', 'SELECT DISTINCT a FROM t ORDER BY a LIMIT 0', Field::Bit];
        yield 'computed ordering' => ['', 'SELECT DISTINCT a FROM t ORDER BY a+0', Field::Bit];
    }

    #[DataProvider('providerIndexes')]
    public function testIndexedRetainsBitFieldsWhenStorageIsUnnecessary(string $index, string $sql, Field $type): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(a BIT(8),b BIT(64)' . $index . ')');
        $result = $session->query($sql)[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame($type, $result->columns[0]->type);
    }

    /**
     * @return iterable<string, array{string, Field}>
     */
    public static function providerHints(): iterable
    {
        yield 'empty selection' => ['USE INDEX()', Field::Long];
        yield 'excluded index' => ['IGNORE INDEX(k)', Field::Long];
        yield 'selected index' => ['USE INDEX(k)', Field::Bit];
        yield 'forced index' => ['FORCE INDEX(k)', Field::Bit];
        yield 'case insensitive' => ['IGNORE INDEX(K)', Field::Long];
        yield 'other selected index' => ['USE INDEX(j)', Field::Long];
        yield 'union of selected indexes' => ['USE INDEX(j) USE INDEX(k)', Field::Bit];
        yield 'ignored selection' => ['USE INDEX(k) IGNORE INDEX(k)', Field::Long];
        yield 'order scope only' => ['IGNORE INDEX FOR ORDER BY(k)', Field::Bit];
        yield 'group scope only' => ['IGNORE INDEX FOR GROUP BY(k)', Field::Bit];
        yield 'join scope only' => ['IGNORE INDEX FOR JOIN(k)', Field::Bit];
        yield 'order and group scopes' => ['IGNORE INDEX FOR ORDER BY(k) IGNORE INDEX FOR GROUP BY(k)', Field::Long];
    }

    #[DataProvider('providerHints')]
    public function testAllowedRespectsTheIndexHintsOfGroupingAndOrdering(string $hint, Field $type): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(a BIT(8),b BIT(64),KEY k(a,b),KEY j(b))');
        $result = $session->query('SELECT DISTINCT a,b FROM t ' . $hint . ' ORDER BY a')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame($type, $result->columns[0]->type);
    }

    /**
     * @return iterable<string, array{string, string, list<Field>}>
     */
    public static function providerOrdering(): iterable
    {
        yield 'mixed scan directions' => ['b BIT(8),KEY k(a,b)', 'a DESC,b ASC', [Field::Long, Field::Long]];
        yield 'reverse scan' => ['b BIT(8),KEY k(a,b)', 'a DESC,b DESC', [Field::Bit, Field::Bit]];
        yield 'descending index' => ['b BIT(8),KEY k(a,b DESC)', 'a ASC,b DESC', [Field::Bit, Field::Bit]];
        yield 'reverse descending index' => ['b BIT(8),KEY k(a,b DESC)', 'a DESC,b ASC', [Field::Bit, Field::Bit]];
        yield 'mismatched descending index' => ['b BIT(8),KEY k(a,b DESC)', 'a,b', [Field::Long, Field::Long]];
        yield 'prefixed selected value' => ['b VARCHAR(10),KEY k(a,b(3))', 'a', [Field::Long, Field::VarString]];
    }

    /**
     * @param list<Field> $types
     */
    #[DataProvider('providerOrdering')]
    public function testOrderedRequiresCompleteValuesAndCompatibleDirections(string $definition, string $order, array $types): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t(a BIT(8),' . $definition . ')');
        $result = $session->query('SELECT DISTINCT a,b FROM t ORDER BY ' . $order)[0];
        self::assertInstanceOf(ResultSet::class, $result);

        self::assertSame($types, array_map(static fn ($column): Field => $column->type, $result->columns));
    }
}
