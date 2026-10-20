<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Show;

use MySqlMemory\Command\Show\ExtendedColumns;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExtendedColumns::class)]
#[Small]
final class ExtendedColumnsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerTables(): iterable
    {
        yield 'ordinary' => ['CREATE TABLE t(a INT)', ['DB_ROW_ID', 'DB_TRX_ID', 'DB_ROLL_PTR']];
        yield 'primary' => ['CREATE TABLE t(a INT PRIMARY KEY)', ['DB_TRX_ID', 'DB_ROLL_PTR']];
        yield 'unique' => ['CREATE TABLE t(a INT NOT NULL UNIQUE)', ['DB_TRX_ID', 'DB_ROLL_PTR']];
        yield 'nullable unique' => ['CREATE TABLE t(a INT UNIQUE)', ['DB_ROW_ID', 'DB_TRX_ID', 'DB_ROLL_PTR']];
        yield 'memory' => ['CREATE TABLE t(a INT) ENGINE=MEMORY', []];
        yield 'myisam' => ['CREATE TABLE t(a INT) ENGINE=MyISAM', []];
        yield 'temporary' => ['CREATE TEMPORARY TABLE t(a INT)', []];
    }

    /**
     * @param list<string> $names
     */
    #[DataProvider('providerTables')]
    public function testRowsFollowTheStorageAndIdentityOfTheTable(string $sql, array $names): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query($sql);
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $rows = (new ExtendedColumns())->rows($table->definition, true);
        self::assertSame($names, array_column($rows, 0));
        self::assertSame(array_fill(0, count($names), 9), array_map(count(...), $rows));
        $compact = (new ExtendedColumns())->rows($table->definition, false);
        self::assertSame($names, array_column($compact, 0));
        self::assertSame(array_fill(0, count($names), 6), array_map(count(...), $compact));
    }
    public function testListingKeepsHiddenFieldsOutOfOrdinaryInspectionsAndFiltersExtendedRows(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT)');
        $ordinary = $session->query('SHOW FULL COLUMNS FROM t')[0];
        $description = $session->query('DESCRIBE t')[0];
        $extended = $session->query("SHOW EXTENDED COLUMNS FROM t LIKE 'DB%'")[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $ordinary);
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $description);
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $extended);
        self::assertSame(['a'], array_column($ordinary->rows, 0));
        self::assertSame(['a'], array_column($description->rows, 0));
        self::assertSame(['DB_ROW_ID', 'DB_TRX_ID', 'DB_ROLL_PTR'], array_column($extended->rows, 0));
    }

}
