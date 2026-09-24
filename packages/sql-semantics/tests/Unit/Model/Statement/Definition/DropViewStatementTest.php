<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Statement\Definition\DropViewStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DropViewStatement::class)]
#[Medium]
final class DropViewStatementTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql, 'DROP VIEW IF EXISTS "v", "w" CASCADE'])]
    #[TestWith([Dialect::MySql, 'DROP VIEW IF EXISTS `v`, `w` CASCADE'])]
    public function testBindsSeveralViewsWithBehavior(Dialect $dialect, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect))->build()))->bind('DROP VIEW IF EXISTS v, w CASCADE');
        self::assertInstanceOf(DropViewStatement::class, $statement);
        self::assertSame([['v'], ['w']], array_map(static fn ($name): array => $name->parts, $statement->names));
        self::assertTrue($statement->ifExists);
        self::assertSame(DropBehavior::Cascade, $statement->behavior);
        self::assertSame(StatementKind::Drop, $statement->kind);
        self::assertSame($expected, $statement->toString());
    }

    public function testWithOriginPreservesTheNamesAndPolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)', 'CREATE VIEW v AS SELECT id FROM t')))->bind('DROP VIEW v');
        self::assertInstanceOf(DropViewStatement::class, $statement);
        $copy = $statement->withOrigin(new Origin('s9', $statement->source, Dialect::Sqlite));
        self::assertNotSame($statement, $copy);
        self::assertSame('s9', $copy->scopeId);
        self::assertSame($statement->names, $copy->names);
        self::assertFalse($copy->ifExists);
        self::assertSame('DROP VIEW "v"', $copy->toString());
    }

    public function testRejectsAnEmptyTargetList(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP VIEW v');
        self::assertInstanceOf(DropViewStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new DropViewStatement($statement->origin, []);
    }
}
