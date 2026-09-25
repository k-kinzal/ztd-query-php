<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\AddColumnStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Constraint\ForeignKey;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AddColumnStatement::class)]
#[Medium]
final class AddColumnStatementTest extends TestCase
{
    public function testBindsTheColumnDeclarationAndItsConstraints(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)', 'CREATE TABLE s(id INTEGER)'));
        $statement = $binder->bind('ALTER TABLE t ADD COLUMN m INTEGER NOT NULL DEFAULT 0 REFERENCES s(id)');
        self::assertInstanceOf(AddColumnStatement::class, $statement);
        self::assertSame(['t'], $statement->table->parts);
        self::assertSame('m', $statement->column->name);
        self::assertSame('integer', $statement->column->type->name);
        self::assertSame(StatementKind::Alter, $statement->kind);
        self::assertFalse($statement->ifNotExists);
        self::assertContainsOnlyInstancesOf(ForeignKey::class, $statement->constraints);
        self::assertSame('ALTER TABLE "t" ADD COLUMN "m" "integer" NOT NULL DEFAULT 0 REFERENCES "s"("id") ON DELETE NO ACTION ON UPDATE NO ACTION', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testWithOriginPreservesTheColumnAndConstraints(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ADD COLUMN m INTEGER');
        self::assertInstanceOf(AddColumnStatement::class, $statement);
        $copy = $statement->withOrigin(new Origin('s9', $statement->source, Dialect::Sqlite));
        self::assertNotSame($statement, $copy);
        self::assertSame('s9', $copy->scopeId);
        self::assertSame($statement->column, $copy->column);
        self::assertSame($statement->constraints, $copy->constraints);
        self::assertSame('ALTER TABLE "t" ADD COLUMN "m" "integer"', (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testRejectsASqliteUniqueConstraintBeforeSerialization(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)'));
        $statement = $binder->bind('ALTER TABLE t ADD COLUMN m INTEGER');
        $declaration = $binder->bind('CREATE TABLE u(id INTEGER, UNIQUE(id))');
        self::assertInstanceOf(AddColumnStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\CreateTableStatement::class, $declaration);
        $this->expectException(InvalidStructure::class);
        new AddColumnStatement($statement->origin, $statement->table, $statement->column, $declaration->definition->table->constraints);
    }
}
