<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\Definition\View\MySqlViewProperties;
use SqlSemantics\Model\Definition\View\PostgreSqlViewProperties;
use SqlSemantics\Model\Definition\ViewCheck;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\CreateViewStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateViewStatement::class)]
#[Medium]
final class CreateViewStatementTest extends TestCase
{
    public function testWithOriginRetainsTheConcreteOperation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (a INT)')))->bind('CREATE OR REPLACE TEMP VIEW v (x) AS SELECT a FROM t WITH LOCAL CHECK OPTION');
        self::assertInstanceOf(CreateViewStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
        self::assertTrue($copy->replace);
        self::assertTrue($copy->temporary);
        self::assertSame(['x'], $copy->columns);
        self::assertSame(ViewCheck::Local, $copy->check);
    }

    public function testWithOriginRejectsAnIncompatibleLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE VIEW v AS SELECT 1');
        self::assertInstanceOf(CreateViewStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithQueryReplacesTheDefinitionWhileKeepingTheDeclaredNames(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (a INT, b INT)'));
        $statement = $binder->bind('CREATE VIEW v (x) AS SELECT a FROM t');
        self::assertInstanceOf(CreateViewStatement::class, $statement);
        $query = $binder->bind('SELECT b FROM t');
        self::assertInstanceOf(BoundQuery::class, $query);
        $changed = $statement->withQuery($query);
        self::assertSame($query->toString(), $changed->query->toString());
        self::assertSame(['x'], $changed->columns);
        self::assertSame('CREATE VIEW `v`(`x`) AS SELECT `b` AS `b` FROM `t`', $changed->toString());
    }

    public function testWithNameChangesTheQualifiedTarget(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE VIEW v AS SELECT 1');
        self::assertInstanceOf(CreateViewStatement::class, $statement);
        $changed = $statement->withName(new QualifiedName(['s', 'w']));
        self::assertSame(['v'], $statement->name->parts);
        self::assertSame(['s', 'w'], $changed->name->parts);
        self::assertSame('CREATE VIEW "s"."w" AS SELECT 1', $changed->toString());
    }

    public function testRejectsPropertiesOfAnotherLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE VIEW v AS SELECT 1');
        self::assertInstanceOf(CreateViewStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new CreateViewStatement($statement->origin, $statement->name, $statement->query, properties: new MySqlViewProperties());
    }

    public function testRejectsARecursiveViewWithoutResultNames(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE VIEW v AS SELECT 1');
        self::assertInstanceOf(CreateViewStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new CreateViewStatement($statement->origin, $statement->name, $statement->query, properties: new PostgreSqlViewProperties(recursive: true));
    }

    public function testRejectsAnExistencePolicyOutsideSqlite(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE VIEW v AS SELECT 1');
        self::assertInstanceOf(CreateViewStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new CreateViewStatement($statement->origin, $statement->name, $statement->query, ifNotExists: true);
    }

    public function testRejectsReplacementForSqlite(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('CREATE VIEW IF NOT EXISTS v AS SELECT 1');
        self::assertInstanceOf(CreateViewStatement::class, $statement);
        self::assertTrue($statement->ifNotExists);
        $this->expectException(InvalidStructure::class);
        new CreateViewStatement($statement->origin, $statement->name, $statement->query, replace: true);
    }

    public function testRejectsACheckOptionForSqlite(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('CREATE VIEW v AS SELECT 1');
        self::assertInstanceOf(CreateViewStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new CreateViewStatement($statement->origin, $statement->name, $statement->query, check: ViewCheck::Cascaded);
    }

    public function testDefaultsToAPlainPermanentView(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (a INT)')))->bind('CREATE VIEW v AS SELECT a FROM t');
        self::assertInstanceOf(CreateViewStatement::class, $statement);
        $rebuilt = new CreateViewStatement($statement->origin, new QualifiedName(['v']), $statement->query);
        self::assertFalse($rebuilt->temporary);
        self::assertFalse($rebuilt->replace);
        self::assertFalse($rebuilt->ifNotExists);
        self::assertSame([], $rebuilt->columns);
        self::assertSame('CREATE VIEW "v" AS SELECT "a" AS "a" FROM "public"."t"', $rebuilt->toString());
    }
}
