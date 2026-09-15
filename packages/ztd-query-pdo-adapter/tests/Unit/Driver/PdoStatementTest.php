<?php

declare(strict_types=1);

namespace Tests\Unit\Driver;

use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\Driver\PdoStatement;
use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversClass(PdoStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\ZtdPdoException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\ZtdPdoStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\ZtdPdo::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Driver\PdoConnection::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\StatementExecution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\Bindings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\BufferedRow::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\DriverSessionFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\ParameterKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\ParameterBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\PreparedQuery::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Adapter\Pdo\Session\ConnectionExecution::class)]
final class PdoStatementTest extends TestCase
{
    public function testImplementsStatementInterface(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE t (id INTEGER)');
        $nativeStmt = $pdo->query('SELECT * FROM t');
        self::assertNotFalse($nativeStmt);

        $stmt = new PdoStatement($nativeStmt);

        self::assertContains(StatementInterface::class, class_implements($stmt));
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

        $resolver = new \ZtdQuery\Platform\Sqlite\SqlitePdoResultColumnTypeResolver();
        $columns = (new PdoStatement($nativeStmt))->resultColumns($resolver);

        self::assertCount(3, $columns);

        self::assertSame(['id', 'name', 'score'], array_map(static fn ($column) => $column->name, $columns));
        self::assertSame(ColumnTypeFamily::INTEGER, $columns[0]->type->family);
        self::assertSame(ColumnTypeFamily::TEXT, $columns[1]->type->family);
        self::assertSame(ColumnTypeFamily::FLOAT, $columns[2]->type->family);
    }

    public function testExecutePreservesTheNativeFailureDetails(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY)');
        $pdo->exec('INSERT INTO users VALUES (1)');
        $native = $pdo->prepare('INSERT INTO users VALUES (1)');
        self::assertNotFalse($native);
        try {
            (new PdoStatement($native))->execute();
            self::fail('A duplicate primary key must fail.');
        } catch (\ZtdQuery\Connection\Exception\DatabaseException $exception) {
            self::assertSame(23000, $exception->getCode());
            self::assertSame(19, $exception->getDriverErrorCode());
            self::assertInstanceOf(PDOException::class, $exception->getPrevious());
        }
    }
    public function testFetchAllPreservesNativeColumnCaseAndDuplicateLabels(): void
    {
        $pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_CASE => PDO::CASE_LOWER]);
        $statement = $pdo->query('SELECT 7 AS MixedName, NULL AS OptionalValue, 9 AS MixedName');
        self::assertNotFalse($statement);
        self::assertSame([['mixedname' => 9, 'optionalvalue' => null]], (new PdoStatement($statement))->fetchAll());
    }


    public function testFetchAllPreservesNumericColumnLabelsAndValueTypes(): void
    {
        $native = new PDO('sqlite::memory:');
        $statement = $native->query('SELECT 7 AS "12", 1.5 AS amount, NULL AS optional');
        self::assertNotFalse($statement);
        self::assertSame([[12 => 7, 'amount' => 1.5, 'optional' => null]], (new PdoStatement($statement))->fetchAll());
    }
}
