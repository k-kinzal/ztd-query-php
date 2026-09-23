<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\MySql as Statement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Statement\DropResourceGroupStatement::class)]
#[Medium]
final class DropResourceGroupStatementTest extends TestCase
{
    public function testWithNameKeepsTheOriginalAndQuotesTheReplacement(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP RESOURCE GROUP target');
        self::assertInstanceOf(Statement\DropResourceGroupStatement::class, $statement);
        $changed = $statement->withName('a`b');
        self::assertSame('target', $statement->name);
        self::assertSame('DROP RESOURCE GROUP `a``b`', $changed->toString());
        self::assertNotSame($statement, $changed);
    }

    public function testWithForceChangesOnlyTheRemovalPolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP RESOURCE GROUP target');
        self::assertInstanceOf(Statement\DropResourceGroupStatement::class, $statement);
        $changed = $statement->withForce(true);
        self::assertFalse($statement->force);
        self::assertTrue($changed->force);
        self::assertSame('DROP RESOURCE GROUP `target` FORCE', $changed->toString());
    }

    public function testWithOriginRetainsTheCompleteRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP RESOURCE GROUP target');
        self::assertInstanceOf(Statement\DropResourceGroupStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->name, $copy->name);
        self::assertSame($statement->force, $copy->force);
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP RESOURCE GROUP target');
        self::assertInstanceOf(Statement\DropResourceGroupStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    public function testWithOriginRejectsReleasesWithoutResourceGroups(string $version): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP RESOURCE GROUP target');
        self::assertInstanceOf(Statement\DropResourceGroupStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

}
