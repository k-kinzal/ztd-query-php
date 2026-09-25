<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\MySql\Storage\CreateUndoTablespaceStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateUndoTablespaceStatement::class)]
#[Medium]
final class CreateUndoTablespaceStatementTest extends TestCase
{
    public function testWithOriginRejectsALegacyRelease(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE UNDO TABLESPACE u ADD DATAFILE 'u.ibu' ENGINE InnoDB");
        self::assertInstanceOf(CreateUndoTablespaceStatement::class, $statement);
        self::assertSame("CREATE UNDO TABLESPACE `u` ADD DATAFILE 'u.ibu' ENGINE = `InnoDB`", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withOrigin($statement->origin)));
        $legacy = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($legacy);
    }

    public function testWithNameKeepsTheDataFile(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE UNDO TABLESPACE u ADD DATAFILE 'u.ibu'");
        self::assertInstanceOf(CreateUndoTablespaceStatement::class, $statement);
        self::assertSame(['v', 'u.ibu'], [$statement->withName('v')->name, $statement->withName('v')->datafile]);
    }

    public function testWithDatafileReplacesTheFile(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE UNDO TABLESPACE u ADD DATAFILE 'u.ibu'");
        self::assertInstanceOf(CreateUndoTablespaceStatement::class, $statement);
        self::assertSame('v.ibu', $statement->withDatafile('v.ibu')->datafile);
    }

    public function testWithEngineRejectsAnEmptyName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE UNDO TABLESPACE u ADD DATAFILE 'u.ibu'");
        self::assertInstanceOf(CreateUndoTablespaceStatement::class, $statement);
        self::assertSame('InnoDB', $statement->withEngine('InnoDB')->engine);
        $this->expectException(InvalidStructure::class);
        $statement->withEngine('');
    }
}
