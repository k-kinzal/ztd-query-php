<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\MySql as Statement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Statement\DropEventStatement::class)]
#[Medium]
final class DropEventStatementTest extends TestCase
{
    public function testWithNameKeepsTheOriginalAndQuotesTheReplacement(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP EVENT target');
        self::assertInstanceOf(Statement\DropEventStatement::class, $statement);
        $changed = $statement->withName(new QualifiedName(['app', 'a`b']));
        self::assertSame(['target'], $statement->name->parts);
        self::assertSame('DROP EVENT `app`.`a``b`', $changed->toString());
        self::assertNotSame($statement, $changed);
    }

    public function testWithIfExistsChangesOnlyTheRemovalPolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP EVENT target');
        self::assertInstanceOf(Statement\DropEventStatement::class, $statement);
        $changed = $statement->withIfExists(true);
        self::assertFalse($statement->ifExists);
        self::assertTrue($changed->ifExists);
        self::assertSame('DROP EVENT IF EXISTS `target`', $changed->toString());
    }

    public function testWithOriginRetainsTheCompleteRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP EVENT target');
        self::assertInstanceOf(Statement\DropEventStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->name, $copy->name);
        self::assertSame($statement->ifExists, $copy->ifExists);
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP EVENT target');
        self::assertInstanceOf(Statement\DropEventStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithNameRejectsAnEventWithMoreThanDatabaseQualification(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP EVENT target');
        self::assertInstanceOf(Statement\DropEventStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withName(new QualifiedName(['a', 'b', 'c']));
    }

}
