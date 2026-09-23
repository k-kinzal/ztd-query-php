<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Storage\CompletionWait;
use SqlSemantics\Model\Statement\Definition\MySql\Storage\DropLogfileGroupStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Storage\DropTablespaceStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Storage\DropUndoTablespaceStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\Definition\Storage\Removals::class)]
#[Medium]
final class RemovalsTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.1.0'])]
    #[TestWith(['mysql-8.2.0'])]
    #[TestWith(['mysql-8.3.0'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.0.1'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindStorageKindsAcrossAllMySqlReleases(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $tablespace = $binder->bind('DROP TABLESPACE store ENGINE NDB NO_WAIT');
        $group = $binder->bind('DROP LOGFILE GROUP logs ENGINE NDB WAIT');
        self::assertInstanceOf(DropTablespaceStatement::class, $tablespace);
        self::assertInstanceOf(DropLogfileGroupStatement::class, $group);
        self::assertSame('store', $tablespace->name);
        self::assertSame('logs', $group->name);
        self::assertSame('NDB', $tablespace->engine);
        self::assertSame(CompletionWait::NoWait, $tablespace->waiting);
        self::assertSame(CompletionWait::Wait, $group->waiting);
    }

    public function testBindUndoRemovalHasItsOwnOperandDomain(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP UNDO TABLESPACE undo1 ENGINE InnoDB');
        self::assertInstanceOf(DropUndoTablespaceStatement::class, $statement);
        self::assertSame('undo1', $statement->name);
        self::assertSame('InnoDB', $statement->engine);
    }

    #[TestWith(['DROP TABLESPACE ``'])]
    #[TestWith(['DROP LOGFILE GROUP ``'])]
    #[TestWith(['DROP UNDO TABLESPACE ``'])]
    public function testBindEmptyStorageIdentityIsInvalidSql(string $sql): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql);
    }
}
