<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Schema\Copy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binding\Schema\Copy\MySqlCopy;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\Column\ComputedColumn;
use SqlSemantics\Schema\Column\SuppliedColumn;
use SqlSemantics\Schema\Constraint\Check;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(MySqlCopy::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class MySqlCopyTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindCopiesColumnsAndIndexesAcrossVersions(string $version): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE app.base (id INTEGER PRIMARY KEY, score INTEGER DEFAULT 7, KEY ix(score))', 'CREATE TABLE archive.copy LIKE app.base');
        $copy = $schema->tables[1];
        self::assertSame('archive', $copy->schema);
        self::assertSame('copy', $copy->name);
        self::assertSame(['id', 'score'], array_column($copy->columns, 'name'));
        self::assertCount(1, $copy->indexes);
        self::assertSame(['archive', 'copy'], $copy->indexes[0]->table);
        self::assertSame('copy', $copy->indexes[0]->elements[0]->value()->lineage()[0]->table->name);
        self::assertSame('archive', $copy->indexes[0]->elements[0]->value()->lineage()[0]->table->schema);
        self::assertInstanceOf(SuppliedColumn::class, $copy->columns[1]->generation);
        self::assertSame('7', $copy->columns[1]->generation->default?->spelling());
    }

    public function testBindCopiesChecksAndGeneratedExpressionsWithoutForeignKeys(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE base (id INTEGER PRIMARY KEY, parent_id INTEGER, doubled INTEGER GENERATED ALWAYS AS (parent_id * 2) STORED, CONSTRAINT ck CHECK (parent_id > 0), FOREIGN KEY (parent_id) REFERENCES parent(id), KEY ix(parent_id))', 'CREATE TABLE copy LIKE base');
        $copy = $schema->tables[1];
        self::assertCount(2, $copy->constraints);
        self::assertInstanceOf(Check::class, $copy->constraints[1]);
        self::assertNull($copy->constraints[1]->name);
        self::assertSame('copy', $copy->constraints[1]->predicate->lineage()[0]->table->name);
        self::assertInstanceOf(ComputedColumn::class, $copy->columns[2]->generation);
        self::assertSame('copy', $copy->columns[2]->generation->expression->lineage()[0]->table->name);
        self::assertSame('base', $schema->tables[0]->indexes[0]->elements[0]->value()->lineage()[0]->table->name);
    }

    public function testBindCopiesTheCurrentDeclarationAfterAlteration(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE base (id INTEGER)', 'ALTER TABLE base ADD COLUMN score INTEGER NOT NULL', 'CREATE TABLE copy LIKE base');
        self::assertSame(['id', 'score'], array_column($schema->tables[1]->columns, 'name'));
        self::assertSame(Nullability::NotNull, $schema->tables[1]->columns[1]->nullability);
    }

    public function testBindDoesNotTreatALikePredicateAsADefinitionCopy(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE parent(id INTEGER)', "CREATE TABLE child(id INTEGER, label TEXT CHECK (label LIKE 'x%'), FOREIGN KEY(id) REFERENCES parent(id))");
        self::assertSame(['id', 'label'], array_column($schema->tables[1]->columns, 'name'));
    }

    public function testBindReusesIndependentCopiedDeclarations(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE base(x INTEGER, d INTEGER GENERATED ALWAYS AS (x*2) STORED, KEY ix(x))', 'CREATE TABLE copy LIKE base', 'CREATE TABLE third LIKE copy');
        self::assertSame('third', $schema->tables[2]->indexes[0]->elements[0]->value()->lineage()[0]->table->name);
        self::assertSame('copy', $schema->tables[1]->indexes[0]->elements[0]->value()->lineage()[0]->table->name);
        self::assertSame('base', $schema->tables[0]->indexes[0]->elements[0]->value()->lineage()[0]->table->name);
    }
}
