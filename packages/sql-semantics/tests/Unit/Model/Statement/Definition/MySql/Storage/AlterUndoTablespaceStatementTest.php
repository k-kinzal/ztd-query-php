<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Storage\UndoTablespaceState;
use SqlSemantics\Model\Statement\Definition\MySql\Storage\AlterUndoTablespaceStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterUndoTablespaceStatement::class)]
#[Medium]
final class AlterUndoTablespaceStatementTest extends TestCase
{
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testWithOriginPreservesTheState(string $version): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind('ALTER UNDO TABLESPACE u SET ACTIVE STORAGE ENGINE = InnoDB');
        self::assertInstanceOf(AlterUndoTablespaceStatement::class, $statement);
        self::assertSame('ALTER UNDO TABLESPACE `u` SET ACTIVE ENGINE = `InnoDB`', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withOrigin($statement->origin)));
    }

    public function testWithNameRejectsAnEmptyName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER UNDO TABLESPACE u SET ACTIVE');
        self::assertInstanceOf(AlterUndoTablespaceStatement::class, $statement);
        self::assertSame('v', $statement->withName('v')->name);
        $this->expectException(InvalidStructure::class);
        $statement->withName('');
    }

    public function testWithStateKeepsTheOriginal(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER UNDO TABLESPACE u SET ACTIVE');
        self::assertInstanceOf(AlterUndoTablespaceStatement::class, $statement);
        self::assertSame(UndoTablespaceState::Inactive, $statement->withState(UndoTablespaceState::Inactive)->state);
        self::assertSame(UndoTablespaceState::Active, $statement->state);
    }

    public function testWithEngineRemovesTheSelection(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER UNDO TABLESPACE u SET ACTIVE ENGINE InnoDB');
        self::assertInstanceOf(AlterUndoTablespaceStatement::class, $statement);
        self::assertSame('ALTER UNDO TABLESPACE `u` SET ACTIVE', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withEngine(null)));
    }
}
