<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Maintenance\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Maintenance\MySql\ChecksumColumn;
use SqlSemantics\Model\Statement\Maintenance\MySql\ChecksumTablesStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ChecksumColumn::class)]
#[Medium]
final class ChecksumColumnTest extends TestCase
{
    public function testInputsContainNoFabricatedRuntimeValues(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, x INT); CREATE TABLE u(id INT, x INT)'));
        $statement = $binder->bind('CHECKSUM TABLE t');
        self::assertInstanceOf(ChecksumTablesStatement::class, $statement);
        $column = $statement->resultColumns()[1]->expression;
        self::assertInstanceOf(ChecksumColumn::class, $column);
        self::assertSame([], $column->inputs());
        self::assertSame($statement->scopeId, $column->scopeId);
    }

    public function testSpellingIdentifiesTheProducedField(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, x INT); CREATE TABLE u(id INT, x INT)'));
        $statement = $binder->bind('CHECKSUM TABLE t');
        self::assertInstanceOf(ChecksumTablesStatement::class, $statement);
        $column = $statement->resultColumns()[1]->expression;
        self::assertInstanceOf(ChecksumColumn::class, $column);
        self::assertSame('Checksum', $column->spelling());
        self::assertSame('bigint unsigned', $column->type->name);
        self::assertSame(\SqlSemantics\Type\Nullability::MaybeNull, $column->nullability);
    }

    public function testWithFactsCannotOverrideTheServerResultType(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, x INT); CREATE TABLE u(id INT, x INT)'));
        $statement = $binder->bind('CHECKSUM TABLE t');
        self::assertInstanceOf(ChecksumTablesStatement::class, $statement);
        $column = $statement->resultColumns()[1]->expression;
        self::assertInstanceOf(ChecksumColumn::class, $column);
        self::assertSame($column->field, $column->withFacts($column->facts)->field);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        $column->withFacts(new \SqlSemantics\Model\Scalar\ExpressionFacts(\SqlSemantics\Type\TypeDescriptor::builtin(Dialect::MySql, 'integer'), \SqlSemantics\Type\Nullability::NotNull));
    }

    public function testRejectsMissingProducingStatementIdentity(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, x INT); CREATE TABLE u(id INT, x INT)'));
        $statement = $binder->bind('CHECKSUM TABLE t');
        self::assertInstanceOf(ChecksumTablesStatement::class, $statement);
        $column = $statement->resultColumns()[1]->expression;
        self::assertInstanceOf(ChecksumColumn::class, $column);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new ChecksumColumn($column->source, '', $column->field);
    }
}
