<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use MySqlMemory\Instance;
use MySqlMemory\Storage\Checksum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Checksum::class)]
#[Small]
final class ChecksumTest extends TestCase
{
    public function testRowsAnswersZeroForAnEmptyTable(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t (a INT)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $table->materialize();

        self::assertSame('0', (new Checksum())->rows($table->definition, $table->data->rows));
    }

    public function testRowsSumsTheRecordChecksumsOfNullableIntegers(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('INSERT INTO t VALUES (1), (2), (NULL)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $table->materialize();

        self::assertSame('3071271071', (new Checksum())->rows($table->definition, $table->data->rows));
    }

    public function testRowsEncodesStringsAndNullBitsAfterNotNullColumns(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t (a INT NOT NULL, b VARCHAR(10))');
        $session->query("INSERT INTO t VALUES (1, 'ab'), (2, NULL)");
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $table->materialize();

        self::assertSame('1669009383', (new Checksum())->rows($table->definition, $table->data->rows));
    }

    public function testRowsPlacesVirtualColumnBitsAfterStoredColumns(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t (a INT, g INT AS (a * 2), h INT AS (a + 1) STORED)');
        $session->query('INSERT INTO t (a) VALUES (1), (2)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $table->materialize();

        self::assertSame('1856912940', (new Checksum())->rows($table->definition, $table->data->rows));
    }

    public function testRecordStartsWithTheNullBitsOfADynamicRowAndUnusedBitsSet(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t (a INT, b VARCHAR(10))');
        $session->query("INSERT INTO t VALUES (1, 'x')");
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $table->materialize();
        $checksum = new Checksum();

        self::assertTrue($checksum->packed($table->definition));
        self::assertSame(chr(0b11111100), $checksum->preamble($table->definition, array_values($table->data->rows)[0]));
        self::assertSame(chr(0b11111100) . "\x01\x00\x00\x00x", $checksum->record($table->definition, array_values($table->data->rows)[0]));
    }

    public function testPreambleReservesTheDeletedBitOfAFixedRow(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t (a INT, b CHAR(2))');
        $session->query("INSERT INTO t VALUES (NULL, 'y')");
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $table->materialize();
        $checksum = new Checksum();

        self::assertFalse($checksum->packed($table->definition));
        self::assertSame(chr(0b11111011), $checksum->preamble($table->definition, array_values($table->data->rows)[0]));
    }

    public function testPreamblePacksMyIsamBitTailsAfterTheNullBit(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t (a BIT(3), b INT) ENGINE=MyISAM');
        $session->query('INSERT INTO t VALUES (5, NULL)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $table->materialize();

        self::assertSame(chr(0b11110101), (new Checksum())->preamble($table->definition, array_values($table->data->rows)[0]));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerPacked(): iterable
    {
        yield 'varchar packs' => ['CREATE TABLE t (a INT, b VARCHAR(10))', true];
        yield 'fixed char keeps the deleted bit' => ['CREATE TABLE t (a INT, b CHAR(10))', false];
        yield 'dynamic row format packs' => ['CREATE TABLE t (a INT, b CHAR(10)) ROW_FORMAT=DYNAMIC', true];
    }

    #[DataProvider('providerPacked')]
    public function testPackedTellsWhetherTheRecordLayoutDropsTheDeletedBit(string $create, bool $packed): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query($create);
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        self::assertSame($packed, (new Checksum())->packed($table->definition));
    }
}
