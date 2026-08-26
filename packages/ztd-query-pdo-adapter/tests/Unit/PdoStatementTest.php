<?php

declare(strict_types=1);

namespace Tests\Unit;

use ZtdQuery\Exception\InvalidDefinitionException;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\PdoStatement;
use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Platform\MissingResultColumnTypeResolver;
use ZtdQuery\Platform\ResultColumnTypeResolver;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversClass(PdoStatement::class)]
final class PdoStatementTest extends TestCase
{
    public function testImplementsStatementInterface(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE t (id INTEGER)');
        $nativeStmt = $pdo->query('SELECT * FROM t');
        self::assertNotFalse($nativeStmt);

        $stmt = new PdoStatement($nativeStmt);

        self::assertInstanceOf(StatementInterface::class, $stmt);
    }

    public function testFetchAllReturnsAssociativeArrays(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE t (id INTEGER, name TEXT)');
        $pdo->exec("INSERT INTO t VALUES (1, 'a')");
        $pdo->exec("INSERT INTO t VALUES (2, 'b')");

        $nativeStmt = $pdo->query('SELECT * FROM t ORDER BY id');
        self::assertNotFalse($nativeStmt);

        $stmt = new PdoStatement($nativeStmt);
        $rows = $stmt->fetchAll();

        self::assertCount(2, $rows);
        self::assertSame(1, $rows[0]['id']);
        self::assertSame('a', $rows[0]['name']);
    }

    public function testRowCountReturnsAffectedRows(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE t (id INTEGER)');
        $pdo->exec('INSERT INTO t VALUES (1)');
        $pdo->exec('INSERT INTO t VALUES (2)');

        $nativeStmt = $pdo->prepare('DELETE FROM t');
        self::assertNotFalse($nativeStmt);
        $nativeStmt->execute();

        $stmt = new PdoStatement($nativeStmt);

        self::assertSame(2, $stmt->rowCount());
    }

    public function testExecuteReturnsTrueOnSuccess(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE t (id INTEGER)');

        $nativeStmt = $pdo->prepare('INSERT INTO t VALUES (1)');
        self::assertNotFalse($nativeStmt);

        $stmt = new PdoStatement($nativeStmt);

        self::assertTrue($stmt->execute());
    }

    public function testResultColumnsDelegateTypesForEmptyResult(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE t (id INTEGER, name TEXT, score REAL)');
        $nativeStmt = $pdo->query('SELECT * FROM t WHERE 1 = 0');
        self::assertNotFalse($nativeStmt);

        $resolver = self::createMock(ResultColumnTypeResolver::class);
        $resolver->expects(self::exactly(3))->method('resolve')->willReturnOnConsecutiveCalls(
            new ColumnType(ColumnTypeFamily::INTEGER, 'INTEGER'),
            new ColumnType(ColumnTypeFamily::TEXT, 'TEXT'),
            new ColumnType(ColumnTypeFamily::FLOAT, 'REAL'),
        );
        $columns = (new PdoStatement($nativeStmt))->resultColumns($resolver);

        self::assertSame(['id', 'name', 'score'], array_map(static fn ($column) => $column->name, $columns));
        self::assertSame(ColumnTypeFamily::INTEGER, $columns[0]->type->family);
        self::assertSame(ColumnTypeFamily::TEXT, $columns[1]->type->family);
        self::assertSame(ColumnTypeFamily::FLOAT, $columns[2]->type->family);
    }

    public function testResultColumnsFailWithoutDialectResolver(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $nativeStmt = $pdo->query('SELECT 1 AS id');
        self::assertNotFalse($nativeStmt);

        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessage('A database platform result column type resolver is required.');

        (new PdoStatement($nativeStmt))->resultColumns(new MissingResultColumnTypeResolver());
    }
}
