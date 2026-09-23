<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Query\Inspection\ProcessColumn;
use SqlSemantics\Model\Statement\Inspection\ShowProcessesStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ProcessColumn::class)]
#[Medium]
final class ProcessColumnTest extends TestCase
{
    public function testInputsContainNoFabricatedRuntimeMetadata(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW PROCESSLIST');
        self::assertInstanceOf(ShowProcessesStatement::class, $statement);
        $column = $statement->resultColumns()[3]->expression;
        self::assertInstanceOf(ProcessColumn::class, $column);
        self::assertSame([], $column->inputs());
        self::assertSame($statement->scopeId, $column->scopeId);
        self::assertSame(\SqlSemantics\Model\ExpressionKind::ServerMetadata, $column->kind);
    }

    public function testSpellingIdentifiesTheProducedField(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW PROCESSLIST');
        self::assertInstanceOf(ShowProcessesStatement::class, $statement);
        $column = $statement->resultColumns()[3]->expression;
        self::assertInstanceOf(ProcessColumn::class, $column);
        self::assertSame('db', $column->spelling());
        self::assertSame(\SqlSemantics\Type\Nullability::MaybeNull, $column->nullability);
    }

    public function testWithFactsRetainsDerivedFieldFacts(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW PROCESSLIST');
        self::assertInstanceOf(ShowProcessesStatement::class, $statement);
        $column = $statement->resultColumns()[3]->expression;
        self::assertInstanceOf(ProcessColumn::class, $column);
        $copy = $column->withFacts($column->facts);
        self::assertSame($column->field, $copy->field);
        self::assertSame($column->scopeId, $copy->scopeId);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        $column->withFacts(new \SqlSemantics\Model\Scalar\ExpressionFacts(\SqlSemantics\Type\TypeDescriptor::builtin(Dialect::MySql, 'integer'), \SqlSemantics\Type\Nullability::NotNull));
    }

    public function testRejectsMissingProducingStatementIdentity(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW PROCESSLIST');
        self::assertInstanceOf(ShowProcessesStatement::class, $statement);
        $column = $statement->resultColumns()[3]->expression;
        self::assertInstanceOf(ProcessColumn::class, $column);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new ProcessColumn($column->source, '', $column->field);
    }

}
