<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\MySqlObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\MySqlObject\ObjectDefinitions;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\MySql\Storage as Statement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ObjectDefinitions::class)]
#[Medium]
final class ObjectDefinitionsTest extends TestCase
{
    #[TestWith(['CREATE TABLESPACE ts', Statement\CreateTablespaceStatement::class])]
    #[TestWith(['ALTER UNDO TABLESPACE u SET ACTIVE', Statement\AlterUndoTablespaceStatement::class])]
    #[TestWith(["CREATE LOGFILE GROUP lg ADD UNDOFILE 'u'", Statement\CreateLogfileGroupStatement::class])]
    public function testBindRoutesEachObjectKind(string $sql, string $class): void
    {
        self::assertSame($class, (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql)::class);
    }

    public function testBindIgnoresOtherStatements(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build();
        $statement = (new Binder($schema))->bind('DROP TABLESPACE ts');
        $context = new \SqlSemantics\Binding\Query\QueryContext(new \SqlSemantics\Binding\TableResolver($schema, new \SqlSemantics\Ast\Identifiers(Dialect::MySql), ''));
        self::assertNull(ObjectDefinitions::bind($statement->origin, $statement->source, $context));
    }
}
