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
}
