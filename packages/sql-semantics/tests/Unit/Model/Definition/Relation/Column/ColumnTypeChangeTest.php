<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Relation\Column\ColumnTypeChange::class)]
#[Medium]
final class ColumnTypeChangeTest extends TestCase
{
    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ALTER COLUMN id SET DATA TYPE text COLLATE "C" USING id::text', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertInstanceOf(Relation\Column\ColumnTypeChange::class, $statement->actions[0]);
        self::assertSame('text', $statement->actions[0]->type->name);
        self::assertSame(['C'], $statement->actions[0]->collation?->parts);
        self::assertNotNull($statement->actions[0]->using);
        self::assertSame('ALTER TABLE "t" ALTER COLUMN "id" TYPE text COLLATE "C" USING CAST("id" AS text)', $statement->toString());
    }

    public function testBindsAPlainTypeChange(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ALTER id TYPE bigint', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertInstanceOf(Relation\Column\ColumnTypeChange::class, $statement->actions[0]);
        self::assertNull($statement->actions[0]->collation);
        self::assertNull($statement->actions[0]->using);
        self::assertSame('ALTER TABLE "t" ALTER COLUMN "id" TYPE bigint', $statement->toString());
    }

    public function testRejectsATypeFromAnotherDatabaseLanguage(): void
    {
        $this->expectException(InvalidStructure::class);
        new Relation\Column\ColumnTypeChange('id', \SqlSemantics\Type\TypeDescriptor::builtin(Dialect::MySql, 'integer'));
    }

    public function testRejectsAnOverQualifiedCollation(): void
    {
        $this->expectException(InvalidStructure::class);
        new Relation\Column\ColumnTypeChange('id', \SqlSemantics\Type\TypeDescriptor::builtin(Dialect::PostgreSql, 'text'), new QualifiedName(['a', 'b', 'c']));
    }
}
