<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Inspection\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Inspection\Replication\ShowBinaryLogsStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ShowBinaryLogsStatement::class)]
#[Medium]
final class ShowBinaryLogsStatementTest extends TestCase
{
    /**
     * @param list<string> $expected
     */
    #[TestWith(['mysql-5.6.51', 'SHOW MASTER LOGS', ['Log_name', 'File_size']])]
    #[TestWith(['mysql-5.7.44', 'SHOW BINARY LOGS', ['Log_name', 'File_size']])]
    #[TestWith(['mysql-8.0.44', 'SHOW MASTER LOGS', ['Log_name', 'File_size', 'Encrypted']])]
    #[TestWith(['mysql-8.4.7', 'SHOW BINARY LOGS', ['Log_name', 'File_size', 'Encrypted']])]
    #[TestWith(['mysql-9.1.0', 'SHOW BINARY LOGS', ['Log_name', 'File_size', 'Encrypted']])]
    public function testResultColumnsFollowTheRelease(string $version, string $sql, array $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(ShowBinaryLogsStatement::class, $statement);
        self::assertSame($expected, array_column($statement->resultColumns(), 'name'));
        self::assertSame('SHOW BINARY LOGS', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testWithOriginKeepsTheRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW BINARY LOGS');
        self::assertInstanceOf(ShowBinaryLogsStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        $this->expectException(InvalidStructure::class);
        new ShowBinaryLogsStatement($statement->origin);
    }
}
