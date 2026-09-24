<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Value\ConfigurationKeyword;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\CreateIndexStatement;
use SqlSemantics\Model\Statement\CreateTableStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\View\CreateMaterializedViewStatement;
use SqlSemantics\Schema\Storage\ImpliedSetting;
use SqlSemantics\Schema\Table\PostgreSqlProperties;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Schema\StorageParameters::class)]
#[Medium]
final class StorageParametersTest extends TestCase
{
    public function testReadKeepsQualifiedNamesAndTypedValues(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE u (a INT) WITH (toast.autovacuum_enabled = false, fillfactor = 70)');
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        self::assertInstanceOf(PostgreSqlProperties::class, $statement->definition->table->properties);
        $parameters = $statement->definition->table->properties->storageParameters;
        self::assertSame([['toast', 'autovacuum_enabled'], ['fillfactor']], array_map(static fn ($parameter): array => $parameter->name->parts, $parameters));
        self::assertInstanceOf(Literal::class, $parameters[1]->value);
    }

    public function testReadTreatsANameWithoutAValueAsEnabled(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (a INT)')))->bind('CREATE INDEX i ON t (a) WITH (fastupdate = off, deduplicate_items)');
        self::assertInstanceOf(CreateIndexStatement::class, $statement);
        $parameters = $statement->index->definition->properties->storageParameters;
        self::assertInstanceOf(ConfigurationKeyword::class, $parameters[0]->value);
        self::assertSame(ImpliedSetting::Enabled, $parameters[1]->value);
    }

    public function testReadStopsAtTheGivenBoundaries(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (a INT)')))->bind('CREATE MATERIALIZED VIEW m WITH (autovacuum_enabled) AS SELECT 1 FROM t WHERE a IN (SELECT 1)');
        self::assertInstanceOf(CreateMaterializedViewStatement::class, $statement);
        self::assertCount(1, $statement->storageParameters);
        self::assertSame(['autovacuum_enabled'], $statement->storageParameters[0]->name->parts);
    }


    public function testReadTakesTheDefinitionElementsOfAConstraint(): void
    {
        $tree = (new \SqlSemantics\Ast\DialectParser(Dialect::PostgreSql))->parse('CREATE TABLE t (a INT, UNIQUE (a) WITH (fillfactor = 70, deduplicate_items))');
        $parameters = \SqlSemantics\Binding\Schema\StorageParameters::read($tree->find('TableConstraint')[0], new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql)), [], 'def_elem');
        self::assertSame([['fillfactor'], ['deduplicate_items']], array_map(static fn ($parameter): array => $parameter->name->parts, $parameters));
    }
}
