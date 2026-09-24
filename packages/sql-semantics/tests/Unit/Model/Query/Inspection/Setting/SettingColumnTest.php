<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Setting;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Query\Inspection\Setting\SettingColumn;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(SettingColumn::class)]
#[Medium]
final class SettingColumnTest extends TestCase
{
    public function testInputsContainNoFabricatedParameterValue(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SHOW work_mem');
        self::assertInstanceOf(\SqlSemantics\Model\ResultStatement::class, $statement);
        $column = $statement->resultColumns()[0]->expression;
        self::assertInstanceOf(SettingColumn::class, $column);
        self::assertSame([], $column->inputs());
        self::assertSame($statement->scopeId, $column->scopeId);
        self::assertSame(ExpressionKind::ServerMetadata, $column->kind);
    }

    public function testSpellingIsTheResultLabel(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SHOW app.mode');
        self::assertInstanceOf(\SqlSemantics\Model\ResultStatement::class, $statement);
        $column = $statement->resultColumns()[0]->expression;
        self::assertInstanceOf(SettingColumn::class, $column);
        self::assertSame('app.mode', $column->spelling());
    }

    public function testWithFactsRetainsDerivedFieldFacts(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SHOW ALL');
        self::assertInstanceOf(\SqlSemantics\Model\ResultStatement::class, $statement);
        $column = $statement->resultColumns()[1]->expression;
        self::assertInstanceOf(SettingColumn::class, $column);
        self::assertSame($column->label, $column->withFacts($column->facts)->label);
        $this->expectException(InvalidStructure::class);
        $column->withFacts(new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'), Nullability::NotNull));
    }

    public function testRejectsAnEmptyLabel(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SHOW work_mem');
        self::assertInstanceOf(\SqlSemantics\Model\ResultStatement::class, $statement);
        $column = $statement->resultColumns()[0]->expression;
        self::assertInstanceOf(SettingColumn::class, $column);
        $this->expectException(InvalidStructure::class);
        new SettingColumn($column->source, $column->scopeId, $column->field, '');
    }

    public function testRejectsMissingProducingStatementIdentity(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SHOW work_mem');
        self::assertInstanceOf(\SqlSemantics\Model\ResultStatement::class, $statement);
        $column = $statement->resultColumns()[0]->expression;
        self::assertInstanceOf(SettingColumn::class, $column);
        $this->expectException(InvalidStructure::class);
        new SettingColumn($column->source, '', $column->field, 'work_mem');
    }
}
