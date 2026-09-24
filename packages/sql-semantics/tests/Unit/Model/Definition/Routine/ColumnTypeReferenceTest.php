<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Routine\ColumnTypeReference::class)]
#[Medium]
final class ColumnTypeReferenceTest extends TestCase
{
    public function testTypeReferenceResolvesTheDeclaredColumn(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
        $binding = new \SqlSemantics\Model\ColumnBinding('r0', $schema->tables[0], $schema->tables[0]->columns[0]);
        $reference = new Routine\ColumnTypeReference(new QualifiedName(['public', 't', 'id']), $binding);
        self::assertSame($binding, $reference->binding);
        self::assertSame(['public', 't', 'id'], $reference->name->parts);
    }

    public function testTypeReferenceRejectsADifferentColumnDeclaration(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
        $binding = new \SqlSemantics\Model\ColumnBinding('r0', $schema->tables[0], $schema->tables[0]->columns[0]);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new Routine\ColumnTypeReference(new QualifiedName(['t', 'value']), $binding);
    }

    public function testTypeReferenceRequiresTableQualification(): void
    {
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new Routine\ColumnTypeReference(new QualifiedName(['id']), null);
    }

    public function testTypeReferenceAcceptsATableQualifiedColumn(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
        $binding = new \SqlSemantics\Model\ColumnBinding('r0', $schema->tables[0], $schema->tables[0]->columns[0]);
        self::assertSame(['t', 'id'], (new Routine\ColumnTypeReference(new QualifiedName(['t', 'id']), $binding))->name->parts);
        self::assertNull((new Routine\ColumnTypeReference(new QualifiedName(['t', 'id']), null))->binding);
    }

    /**
     * @param list<string> $parts
     */
    #[\PHPUnit\Framework\Attributes\TestWith([['u', 'id']])]
    #[\PHPUnit\Framework\Attributes\TestWith([['other', 't', 'id']])]
    #[\PHPUnit\Framework\Attributes\TestWith([['db', 'public', 't', 'id']])]
    public function testTypeReferenceRejectsANameOfAnotherDeclaration(array $parts): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
        $binding = new \SqlSemantics\Model\ColumnBinding('r0', $schema->tables[0], $schema->tables[0]->columns[0]);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new Routine\ColumnTypeReference(new QualifiedName($parts), $binding);
    }

    public function testTypeReferenceRejectsAColumnOfAnotherDialect(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INTEGER)');
        $binding = new \SqlSemantics\Model\ColumnBinding('r0', $schema->tables[0], $schema->tables[0]->columns[0]);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new Routine\ColumnTypeReference(new QualifiedName(['t', 'id']), $binding);
    }
}
