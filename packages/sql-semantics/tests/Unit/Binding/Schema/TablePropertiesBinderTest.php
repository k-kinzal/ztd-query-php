<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\Table\CommitAction;
use SqlSemantics\Schema\Table\MySqlProperties;
use SqlSemantics\Schema\Table\Persistence;
use SqlSemantics\Schema\Table\PostgreSqlProperties;
use SqlSemantics\Schema\Table\SqliteProperties;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Schema\TablePropertiesBinder::class)]
#[Medium]
final class TablePropertiesBinderTest extends TestCase
{
    public function testBindClassifiesSqliteOptions(): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TEMP TABLE t(id INTEGER PRIMARY KEY) WITHOUT ROWID, STRICT; CREATE TABLE u(id INTEGER)');
        $temporary = $schema->tables[0]->properties;
        self::assertInstanceOf(SqliteProperties::class, $temporary);
        self::assertTrue($temporary->withoutRowId);
        self::assertTrue($temporary->strict);
        self::assertTrue($temporary->temporary);
        $plain = $schema->tables[1]->properties;
        self::assertInstanceOf(SqliteProperties::class, $plain);
        self::assertFalse($plain->withoutRowId);
        self::assertFalse($plain->strict);
        self::assertFalse($plain->temporary);
    }

    public function testBindClassifiesPostgreSqlPersistenceAndStorage(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE UNLOGGED TABLE t(id INTEGER) WITH (fillfactor=70) TABLESPACE ts; CREATE TEMP TABLE u(id INTEGER) ON COMMIT DROP; CREATE TABLE v(id INTEGER)');
        $unlogged = $schema->tables[0]->properties;
        self::assertInstanceOf(PostgreSqlProperties::class, $unlogged);
        self::assertSame(Persistence::Unlogged, $unlogged->persistence);
        self::assertSame(CommitAction::PreserveRows, $unlogged->onCommit);
        self::assertSame('ts', $unlogged->tablespace);
        self::assertSame(['fillfactor'], array_map(static fn (\SqlSemantics\Schema\Storage\Parameter $parameter): string => implode('.', $parameter->name->parts), $unlogged->storageParameters));
        $temporary = $schema->tables[1]->properties;
        self::assertInstanceOf(PostgreSqlProperties::class, $temporary);
        self::assertSame(Persistence::Temporary, $temporary->persistence);
        self::assertSame(CommitAction::Drop, $temporary->onCommit);
        $permanent = $schema->tables[2]->properties;
        self::assertInstanceOf(PostgreSqlProperties::class, $permanent);
        self::assertSame(Persistence::Permanent, $permanent->persistence);
        self::assertNull($permanent->tablespace);
    }

    public function testBindDelegatesMySqlOptions(): void
    {
        $properties = (new SchemaBuilder(Dialect::MySql))->build("CREATE TEMPORARY TABLE t(id INT) ENGINE=InnoDB COMMENT='c'")->tables[0]->properties;
        self::assertInstanceOf(MySqlProperties::class, $properties);
        self::assertTrue($properties->temporary);
        self::assertSame('InnoDB', $properties->engine);
        self::assertSame('c', $properties->comment);
    }

    public function testBindDiagnosesStorageParametersOfAPartitionedTable(): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::PartitionedTable->message());
        (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE p(id INTEGER) PARTITION BY RANGE (id) WITH (fillfactor = 70)');
    }

    public function testBindDiagnosesAPartitionedTableThatInherits(): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::PartitionedTable->message());
        (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE a(id INTEGER)', 'CREATE TABLE p(id INTEGER) INHERITS (a) PARTITION BY RANGE (id)');
    }

    public function testParentsReadsInheritsInOrder(): void
    {
        $properties = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE a(x INTEGER); CREATE TABLE b(y INTEGER)', 'CREATE TABLE c(z INTEGER) INHERITS (b, a)')->tables[2]->properties;
        self::assertInstanceOf(PostgreSqlProperties::class, $properties);
        self::assertSame([['b'], ['a']], array_map(static fn (\SqlSemantics\Model\Relation\QualifiedName $parent): array => $parent->parts, $properties->parents));
    }

    public function testParentsDiagnoseAParentNamedTwice(): void
    {
        $binder = new \SqlSemantics\Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE a(x INTEGER)'));
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::InheritedParent->message());
        $binder->bind('CREATE TABLE c(z INTEGER) INHERITS (a, a)');
    }

    public function testParentsDiagnoseAParentNameWithTooManyComponents(): void
    {
        $binder = new \SqlSemantics\Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::CatalogObjectName->message());
        $binder->bind('CREATE TABLE c(z INTEGER) INHERITS (w.x.y.z)');
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['create temp table t(a)', true])]
    #[\PHPUnit\Framework\Attributes\TestWith(["CREATE TABLE t(a DEFAULT 'CREATE TEMP ')", false])]
    public function testBindReadsSqliteTemporaryTablesFromTheHead(string $sql, bool $temporary): void
    {
        $properties = (new SchemaBuilder(Dialect::Sqlite))->build($sql)->tables[0]->properties;
        self::assertInstanceOf(SqliteProperties::class, $properties);
        self::assertSame($temporary, $properties->temporary);
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['create temp table t(a int) on commit delete rows', Persistence::Temporary, CommitAction::DeleteRows])]
    #[\PHPUnit\Framework\Attributes\TestWith(['CREATE TABLE temp_x(a int)', Persistence::Permanent, CommitAction::PreserveRows])]
    public function testBindReadsPostgreSqlPersistenceBeforeTheTableKeyword(string $sql, Persistence $persistence, CommitAction $onCommit): void
    {
        $properties = (new SchemaBuilder(Dialect::PostgreSql))->build($sql)->tables[0]->properties;
        self::assertInstanceOf(PostgreSqlProperties::class, $properties);
        self::assertSame([$persistence, $onCommit], [$properties->persistence, $properties->onCommit]);
    }

    public function testParentsAcceptsACatalogQualifiedParent(): void
    {
        $statement = (new \SqlSemantics\Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE p(a int)')))->bind('CREATE TABLE c(a int) INHERITS (db.public.p)', strict: false);
        self::assertSame('CREATE TABLE "public"."c"("a" integer) INHERITS("db"."public"."p")', $statement->toString());
    }

    public function testParentsRejectsAParentWithFourComponents(): void
    {
        $binder = new \SqlSemantics\Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE p(a int)'));
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage('The object name does not have the number of components its object class requires.');
        $binder->bind('CREATE TABLE c(a int) INHERITS (a.db.public.p)', strict: false);
    }
}
