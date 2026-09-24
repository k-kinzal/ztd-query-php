<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\MySqlObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @return list<array{Dialect, ?string, string, mixed}>
     */
    public static function providerBindRoutesEachObjectDefinition(): array
    {
        return [
            [Dialect::MySql, null, 'create tablespace ts ADD DATAFILE \'a.ibd\'', [Statement\CreateTablespaceStatement::class, 'CREATE TABLESPACE `ts` ADD DATAFILE \'a.ibd\' WAIT']],
            [Dialect::MySql, null, 'create undo tablespace u ADD DATAFILE \'u.ibu\'', [Statement\CreateUndoTablespaceStatement::class, 'CREATE UNDO TABLESPACE `u` ADD DATAFILE \'u.ibu\'']],
            [Dialect::MySql, null, 'ALTER UNDO TABLESPACE u SET INACTIVE', [Statement\AlterUndoTablespaceStatement::class, 'ALTER UNDO TABLESPACE `u` SET INACTIVE']],
            [Dialect::MySql, null, 'create server s FOREIGN DATA WRAPPER mysql OPTIONS (HOST \'h\')', [\SqlSemantics\Model\Statement\Definition\MySql\Server\CreateServerStatement::class, 'CREATE SERVER `s` FOREIGN DATA WRAPPER `mysql` OPTIONS(HOST \'h\')']],
            [Dialect::MySql, null, 'ALTER SERVER s OPTIONS (HOST \'h\')', [\SqlSemantics\Model\Statement\Definition\MySql\Server\AlterServerStatement::class, 'ALTER SERVER `s` OPTIONS(HOST \'h\')']],
            [Dialect::MySql, null, 'alter view v AS SELECT 1', [\SqlSemantics\Model\Statement\Definition\MySql\View\AlterViewStatement::class, 'ALTER VIEW `v` AS SELECT 1']],
            [Dialect::MySql, null, 'CREATE FUNCTION f RETURNS STRING SONAME \'f.so\'', [\SqlSemantics\Model\Statement\Definition\MySql\Server\CreateLoadableFunctionStatement::class, 'CREATE FUNCTION `f` RETURNS STRING SONAME \'f.so\'']],
            [Dialect::MySql, null, 'CREATE AGGREGATE FUNCTION f RETURNS INTEGER SONAME \'f.so\'', [\SqlSemantics\Model\Statement\Definition\MySql\Server\CreateLoadableFunctionStatement::class, 'CREATE AGGREGATE FUNCTION `f` RETURNS INTEGER SONAME \'f.so\'']],
        ];
    }

    #[DataProvider('providerBindRoutesEachObjectDefinition')]
    public function testBindRoutesEachObjectDefinition(Dialect $dialect, ?string $version, string $sql, mixed $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build('CREATE VIEW v AS SELECT 1')))->bind($sql, strict: false);
        self::assertSame($expected, [$statement::class, $statement->toString()]);
    }
}
