<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Query\Inspection\Field\Schema\DatabaseField;
use SqlSemantics\Model\Query\Inspection\MetadataColumn;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Statement\Inspection\Schema\ShowDatabasesStatement;
use SqlSemantics\Model\Statement\Inspection\Schema\ShowTablesStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(MetadataColumn::class)]
#[Medium]
final class MetadataColumnTest extends TestCase
{
    public function testInputsContainNoFabricatedRuntimeMetadata(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW DATABASES');
        self::assertInstanceOf(ShowDatabasesStatement::class, $statement);
        $column = $statement->resultColumns()[0]->expression;
        self::assertInstanceOf(MetadataColumn::class, $column);
        self::assertSame([], $column->inputs());
        self::assertSame($statement->scopeId, $column->scopeId);
        self::assertSame(DatabaseField::Name, $column->field);
        self::assertSame(ExpressionKind::ServerMetadata, $column->kind);
        self::assertSame('varchar', $column->type->name);
        self::assertSame(Nullability::NotNull, $column->nullability);
    }

    public function testSpellingUsesTheRequestLabelWhenItExtendsTheFieldLabel(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW TABLES FROM app');
        self::assertInstanceOf(ShowTablesStatement::class, $statement);
        $column = $statement->resultColumns()[0]->expression;
        self::assertInstanceOf(MetadataColumn::class, $column);
        self::assertSame('Tables_in_app', $column->spelling());
        self::assertSame('Tables_in_app', $column->label);
        self::assertSame('Tables_in_', $column->field->label());
    }

    public function testWithFactsRetainsDerivedFieldFacts(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW TABLES FROM app');
        self::assertInstanceOf(ShowTablesStatement::class, $statement);
        $column = $statement->resultColumns()[0]->expression;
        self::assertInstanceOf(MetadataColumn::class, $column);
        $copy = $column->withFacts($column->facts);
        self::assertSame($column->field, $copy->field);
        self::assertSame($column->label, $copy->label);
        self::assertSame($column->scopeId, $copy->scopeId);
        $this->expectException(InvalidStructure::class);
        $column->withFacts(new ExpressionFacts(TypeDescriptor::builtin(Dialect::MySql, 'integer'), Nullability::NotNull));
    }

    public function testRejectsMissingProducingStatementIdentity(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW DATABASES');
        self::assertInstanceOf(ShowDatabasesStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new MetadataColumn($statement->source, '', DatabaseField::Name);
    }

    public function testRejectsAnEmptyResultLabel(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW DATABASES');
        self::assertInstanceOf(ShowDatabasesStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new MetadataColumn($statement->source, $statement->scopeId, DatabaseField::Name, '');
    }
}
