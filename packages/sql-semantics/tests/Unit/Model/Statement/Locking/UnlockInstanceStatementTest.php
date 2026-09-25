<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Locking;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Locking\UnlockInstanceStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(UnlockInstanceStatement::class)]
#[Medium]
final class UnlockInstanceStatementTest extends TestCase
{
    public function testWithOriginRetainsTheReleaseRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.0.44'))->build()))->bind('unlock instance');
        self::assertInstanceOf(UnlockInstanceStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame('UNLOCK INSTANCE', (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testRejectsAReleaseWithoutBackupLocks(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.0.44'))->build()))->bind('UNLOCK INSTANCE');
        $legacy = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1');
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin(new Origin('s0', $statement->source, Dialect::MySql, [], $legacy->origin->context));
    }

    public function testRejectsAnotherDatabaseDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('UNLOCK INSTANCE');
        $this->expectException(InvalidStructure::class);
        new UnlockInstanceStatement(new Origin('s0', $statement->source, Dialect::Sqlite));
    }
}
