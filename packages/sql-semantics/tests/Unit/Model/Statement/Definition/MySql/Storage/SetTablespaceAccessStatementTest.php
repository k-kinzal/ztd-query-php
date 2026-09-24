<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Storage\TablespaceAccess;
use SqlSemantics\Model\Statement\Definition\MySql\Storage\SetTablespaceAccessStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SetTablespaceAccessStatement::class)]
#[Medium]
final class SetTablespaceAccessStatementTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    public function testWithOriginRejectsAModernRelease(string $version): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind('ALTER TABLESPACE ts NOT ACCESSIBLE');
        self::assertInstanceOf(SetTablespaceAccessStatement::class, $statement);
        self::assertSame(TablespaceAccess::NotAccessible, $statement->withOrigin($statement->origin)->access);
        $modern = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($modern);
    }

    public function testWithNameKeepsTheAccessMode(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('ALTER TABLESPACE ts READ_WRITE');
        self::assertInstanceOf(SetTablespaceAccessStatement::class, $statement);
        self::assertSame('ALTER TABLESPACE `t2` READ_WRITE', $statement->withName('t2')->toString());
    }

    public function testWithAccessReplacesTheMode(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('ALTER TABLESPACE ts READ_WRITE');
        self::assertInstanceOf(SetTablespaceAccessStatement::class, $statement);
        self::assertSame('ALTER TABLESPACE `ts` READ_ONLY', $statement->withAccess(TablespaceAccess::ReadOnly)->toString());
        self::assertSame(TablespaceAccess::ReadWrite, $statement->access);
    }
}
