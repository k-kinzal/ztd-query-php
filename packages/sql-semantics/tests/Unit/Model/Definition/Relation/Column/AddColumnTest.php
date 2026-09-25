<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Relation\Column\AddColumn::class)]
#[Medium]
final class AddColumnTest extends TestCase
{
    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ADD COLUMN IF NOT EXISTS n integer NOT NULL DEFAULT 0 CHECK (n >= 0)', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertInstanceOf(Relation\Column\AddColumn::class, $statement->actions[0]);
        self::assertSame('n', $statement->actions[0]->column->name);
        self::assertTrue($statement->actions[0]->ifNotExists);
        self::assertCount(1, $statement->actions[0]->constraints);
        self::assertSame([], $statement->actions[0]->options);
        self::assertSame('ALTER TABLE "t" ADD COLUMN IF NOT EXISTS "n" integer NOT NULL DEFAULT 0 CHECK (("n" >= 0))', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testBindsWrapperOptionsOfAForeignTableColumn(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind("ALTER FOREIGN TABLE t ADD COLUMN n integer OPTIONS (column_name 'x') NOT NULL", strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertInstanceOf(Relation\Column\AddColumn::class, $statement->actions[0]);
        self::assertSame('column_name', $statement->actions[0]->options[0]->name);
        self::assertSame('ALTER FOREIGN TABLE "t" ADD COLUMN "n" integer OPTIONS("column_name" \'x\') NOT NULL', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testRejectsAColumnFromAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ADD COLUMN n integer');
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertInstanceOf(Relation\Column\AddColumn::class, $statement->actions[0]);
        $source = $statement->actions[0]->column;
        $column = new \SqlSemantics\Schema\ColumnDefinition('n', \SqlSemantics\Type\TypeDescriptor::builtin(Dialect::MySql, 'integer'), $source->nullability, $source->source);
        $this->expectException(InvalidStructure::class);
        new Relation\Column\AddColumn($column);
    }
}
