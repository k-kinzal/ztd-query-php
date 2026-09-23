<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Model\Statement\Definition\MySql\DropFunctionStatement::class)]
#[Medium]
final class DropFunctionStatementTest extends TestCase
{
    public function testWithNamePreservesTheOriginalAndQuotesTheNewIdentifier(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP FUNCTION app.f');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\MySql\DropFunctionStatement::class, $statement);
        $changed = $statement->withName(new QualifiedName(['a`b; DROP TABLE t']));
        self::assertSame(['app', 'f'], $statement->name->parts);
        self::assertSame('DROP FUNCTION `a``b; DROP TABLE t`', $changed->toString());
    }

    public function testWithNameRejectsMoreThanDatabaseQualification(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP FUNCTION app.f');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\MySql\DropFunctionStatement::class, $statement);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        $statement->withName(new QualifiedName(['a', 'b', 'c']));
    }

    public function testWithIfExistsChangesTheExistencePolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP FUNCTION app.f');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\MySql\DropFunctionStatement::class, $statement);
        $changed = $statement->withIfExists(true);
        self::assertFalse($statement->ifExists);
        self::assertSame('DROP FUNCTION IF EXISTS `app`.`f`', $changed->toString());
    }

    public function testWithOriginRetainsTheCompleteRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP FUNCTION app.f');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\MySql\DropFunctionStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->name, $copy->name);
        self::assertFalse($copy->ifExists);
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP FUNCTION app.f');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\MySql\DropFunctionStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        $statement->withOrigin($origin);
    }

}
