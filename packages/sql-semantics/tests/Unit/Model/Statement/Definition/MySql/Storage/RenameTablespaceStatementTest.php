<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\MySql\Storage\RenameTablespaceStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RenameTablespaceStatement::class)]
#[Medium]
final class RenameTablespaceStatementTest extends TestCase
{
    public function testWithOriginPreservesBothNames(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.0.44'))->build()))->bind('ALTER TABLESPACE a RENAME TO b');
        self::assertInstanceOf(RenameTablespaceStatement::class, $statement);
        self::assertSame('ALTER TABLESPACE `a` RENAME TO `b`', $statement->withOrigin($statement->origin)->toString());
    }

    public function testWithNameReplacesTheSource(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER TABLESPACE a RENAME TO b');
        self::assertInstanceOf(RenameTablespaceStatement::class, $statement);
        self::assertSame(['c', 'b'], [$statement->withName('c')->name, $statement->newName]);
    }

    public function testWithNewNameRejectsAnEmptyTarget(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER TABLESPACE a RENAME TO b');
        self::assertInstanceOf(RenameTablespaceStatement::class, $statement);
        self::assertSame('c', $statement->withNewName('c')->newName);
        $this->expectException(InvalidStructure::class);
        $statement->withNewName('');
    }
}
