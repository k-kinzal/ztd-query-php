<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Inspection\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Inspection\Replication\ShowBinaryLogStatusStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ShowBinaryLogStatusStatement::class)]
#[Medium]
final class ShowBinaryLogStatusStatementTest extends TestCase
{
    #[TestWith(['mysql-5.6.51', 'SHOW MASTER STATUS', 'SHOW MASTER STATUS'])]
    #[TestWith(['mysql-8.0.44', 'SHOW MASTER STATUS', 'SHOW MASTER STATUS'])]
    #[TestWith(['mysql-8.2.0', 'SHOW BINARY LOG STATUS', 'SHOW MASTER STATUS'])]
    #[TestWith(['mysql-8.4.7', 'SHOW BINARY LOG STATUS', 'SHOW BINARY LOG STATUS'])]
    #[TestWith(['mysql-9.1.0', 'SHOW BINARY LOG STATUS', 'SHOW BINARY LOG STATUS'])]
    public function testBinaryLogSpellingFollowsTheRelease(string $version, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(ShowBinaryLogStatusStatement::class, $statement);
        self::assertSame($expected === 'SHOW BINARY LOG STATUS', $statement->binaryLogSpelling());
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testResultColumnsListTheCurrentPosition(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW BINARY LOG STATUS');
        self::assertInstanceOf(ShowBinaryLogStatusStatement::class, $statement);
        self::assertSame(['File', 'Position', 'Binlog_Do_DB', 'Binlog_Ignore_DB', 'Executed_Gtid_Set'], array_column($statement->resultColumns(), 'name'));
        self::assertSame('bigint', $statement->resultColumns()[1]->expression->type->name);
    }

    public function testWithOriginKeepsTheRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW BINARY LOG STATUS');
        self::assertInstanceOf(ShowBinaryLogStatusStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1');
        $this->expectException(InvalidStructure::class);
        new ShowBinaryLogStatusStatement($statement->origin);
    }
}
