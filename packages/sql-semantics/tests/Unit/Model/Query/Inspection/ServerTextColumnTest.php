<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Query\Inspection\ServerTextColumn;
use SqlSemantics\Model\Statement\Inspection\ShowPluginsStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ServerTextColumn::class)]
#[Medium]
final class ServerTextColumnTest extends TestCase
{
    public function testInputsContainNoFabricatedRuntimeMetadata(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW PLUGINS');
        self::assertInstanceOf(ShowPluginsStatement::class, $statement);
        $column = $statement->resultColumns()[3]->expression;
        self::assertInstanceOf(ServerTextColumn::class, $column);
        self::assertSame([], $column->inputs());
        self::assertSame($statement->scopeId, $column->scopeId);
        self::assertSame(\SqlSemantics\Model\ExpressionKind::ServerMetadata, $column->kind);
    }

    public function testSpellingIdentifiesTheProducedField(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW PLUGINS');
        self::assertInstanceOf(ShowPluginsStatement::class, $statement);
        $column = $statement->resultColumns()[3]->expression;
        self::assertInstanceOf(ServerTextColumn::class, $column);
        self::assertSame('Library', $column->spelling());
        self::assertSame(\SqlSemantics\Type\Nullability::MaybeNull, $column->nullability);
    }

    public function testWithFactsRetainsDerivedFieldFacts(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW PLUGINS');
        self::assertInstanceOf(ShowPluginsStatement::class, $statement);
        $column = $statement->resultColumns()[3]->expression;
        self::assertInstanceOf(ServerTextColumn::class, $column);
        $copy = $column->withFacts($column->facts);
        self::assertSame($column->field, $copy->field);
        self::assertSame($column->scopeId, $copy->scopeId);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        $column->withFacts(new \SqlSemantics\Model\Scalar\ExpressionFacts(\SqlSemantics\Type\TypeDescriptor::builtin(Dialect::MySql, 'integer'), \SqlSemantics\Type\Nullability::NotNull));
    }

    public function testRejectsMissingProducingStatementIdentity(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW PLUGINS');
        self::assertInstanceOf(ShowPluginsStatement::class, $statement);
        $column = $statement->resultColumns()[3]->expression;
        self::assertInstanceOf(ServerTextColumn::class, $column);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new ServerTextColumn($column->source, '', $column->field);
    }

}
