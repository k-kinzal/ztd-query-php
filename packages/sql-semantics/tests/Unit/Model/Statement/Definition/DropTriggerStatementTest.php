<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\DropTriggerStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DropTriggerStatement::class)]
#[Medium]
final class DropTriggerStatementTest extends TestCase
{
    #[TestWith([Dialect::MySql, 'DROP TRIGGER IF EXISTS `app`.`renamed`'])]
    #[TestWith([Dialect::Sqlite, 'DROP TRIGGER IF EXISTS "app"."renamed"'])]
    public function testWithNameReplacesOneQualifiedTrigger(Dialect $dialect, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect))->build()))->bind('DROP TRIGGER IF EXISTS app.original');
        self::assertInstanceOf(DropTriggerStatement::class, $statement);
        $changed = $statement->withName(new QualifiedName(['app', 'renamed']));
        self::assertSame($expected, $changed->toString());
        self::assertSame(['app', 'original'], $statement->name->parts);
    }

    public function testWithIfExistsChangesTheMissingTriggerPolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP TRIGGER audit');
        self::assertInstanceOf(DropTriggerStatement::class, $statement);
        $changed = $statement->withIfExists(true);
        self::assertFalse($statement->ifExists);
        self::assertTrue($changed->ifExists);
        self::assertSame('DROP TRIGGER IF EXISTS `audit`', $changed->toString());
    }

    public function testWithOriginRetainsTheSingleTarget(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('DROP TRIGGER IF EXISTS main.audit');
        self::assertInstanceOf(DropTriggerStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->name, $copy->name);
        self::assertSame($statement->ifExists, $copy->ifExists);
    }

    public function testWithNameRejectsAnImpossibleTriggerQualification(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP TRIGGER audit');
        self::assertInstanceOf(DropTriggerStatement::class, $statement);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        $statement->withName(new QualifiedName(['a', 'b', 'c']));
    }

    public function testRejectsAnOwnerlessPostgreSqlDeletion(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP TRIGGER audit ON t')->origin;
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new DropTriggerStatement($origin, new QualifiedName(['audit']));
    }

}
