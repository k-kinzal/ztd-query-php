<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Stored\EventAlteration;
use SqlSemantics\Model\Definition\Routine\Stored\EventStatus;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\MySql\Program\AlterEventStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterEventStatement::class)]
#[Medium]
final class AlterEventStatementTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindsTheSameChangesOnEveryRelease(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind("ALTER DEFINER = 'ops'@'%' EVENT e ON SCHEDULE EVERY 2 WEEK ON COMPLETION NOT PRESERVE RENAME TO archive.e ENABLE COMMENT 'weekly' DO BEGIN DECLARE x INT; SET x = 1; END");
        self::assertInstanceOf(AlterEventStatement::class, $statement);
        self::assertSame(StatementKind::Alter, $statement->kind);
        self::assertSame(['archive', 'e'], $statement->changes->newName?->parts);
        self::assertSame("ALTER DEFINER = 'ops' @'%' EVENT `e` ON SCHEDULE EVERY 2 WEEK ON COMPLETION NOT PRESERVE RENAME TO `archive`.`e` ENABLE COMMENT 'weekly' DO BEGIN DECLARE `x` integer; SET `x` = 1; END", $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testWithOriginPreservesTheChanges(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER EVENT e DISABLE');
        self::assertInstanceOf(AlterEventStatement::class, $statement);
        self::assertSame($statement->toString(), $statement->withOrigin($statement->origin)->toString());
    }

    public function testWithNameReplacesTheTarget(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER EVENT e DISABLE');
        self::assertInstanceOf(AlterEventStatement::class, $statement);
        self::assertSame('ALTER EVENT `db`.`e` DISABLE', $statement->withName(new QualifiedName(['db', 'e']))->toString());
    }

    public function testWithChangesReplacesAllChanges(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER EVENT e DISABLE');
        self::assertInstanceOf(AlterEventStatement::class, $statement);
        self::assertSame('ALTER EVENT `e` ENABLE', $statement->withChanges(new EventAlteration(status: EventStatus::Enabled))->toString());
    }

    public function testWithDefinerRemovesTheDefiner(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER DEFINER = CURRENT_USER EVENT e DISABLE');
        self::assertInstanceOf(AlterEventStatement::class, $statement);
        self::assertSame('ALTER EVENT `e` DISABLE', $statement->withDefiner(null)->toString());
    }

    public function testRejectsAThreePartName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER EVENT e DISABLE');
        self::assertInstanceOf(AlterEventStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new AlterEventStatement($statement->origin, new QualifiedName(['a', 'b', 'c']), $statement->changes);
    }
}
