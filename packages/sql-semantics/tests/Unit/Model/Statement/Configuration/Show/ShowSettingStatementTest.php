<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Configuration\Show;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Query\Inspection\Setting\SettingColumn;
use SqlSemantics\Model\Query\Inspection\Setting\SettingField;
use SqlSemantics\Model\Statement\Configuration\Show\ShowSettingStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(ShowSettingStatement::class)]
#[Medium]
final class ShowSettingStatementTest extends TestCase
{
    public function testWithOriginRetainsTheParameter(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SHOW app.mode');
        self::assertInstanceOf(ShowSettingStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertSame(['app', 'mode'], $copy->name);
        self::assertSame(StatementKind::Show, $copy->kind);
    }

    public function testWithNameDisplaysAnotherParameter(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SHOW work_mem');
        self::assertInstanceOf(ShowSettingStatement::class, $statement);
        $changed = $statement->withName(['search_path']);
        self::assertSame('SHOW "search_path"', $changed->toString());
        self::assertSame(['work_mem'], $statement->name);
    }

    public function testResultColumnsDeclareOneTextValue(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SHOW TIME ZONE');
        self::assertInstanceOf(ShowSettingStatement::class, $statement);
        $columns = $statement->resultColumns();
        self::assertCount(1, $columns);
        self::assertSame('timezone', $columns[0]->name);
        $column = $columns[0]->expression;
        self::assertInstanceOf(SettingColumn::class, $column);
        self::assertSame('text', $column->type->name);
        self::assertSame(Nullability::NotNull, $column->nullability);
        self::assertSame(SettingField::Setting, $column->field);
    }

    public function testRejectsTheAllParameterName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SHOW work_mem');
        $this->expectException(InvalidStructure::class);
        new ShowSettingStatement($statement->origin, ['All']);
    }

    public function testRejectsAnEmptyNamePart(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SHOW work_mem');
        $this->expectException(InvalidStructure::class);
        new ShowSettingStatement($statement->origin, ['a', '']);
    }

    public function testRequiresPostgreSql(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SHOW work_mem');
        $this->expectException(InvalidStructure::class);
        new ShowSettingStatement(new Origin($statement->origin->scopeId, $statement->origin->source, Dialect::MySql), ['a']);
    }
}
