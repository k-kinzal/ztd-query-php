<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Procedural;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Condition\ConditionDiagnostic;
use SqlSemantics\Model\Configuration\Condition\ConditionItem;
use SqlSemantics\Model\Configuration\Condition\DiagnosticsArea;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Procedural\GetConditionDiagnosticsStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(GetConditionDiagnosticsStatement::class)]
#[Medium]
final class GetConditionDiagnosticsStatementTest extends TestCase
{
    public function testWithOriginRetainsConditionAndItems(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('GET DIAGNOSTICS CONDITION 1 @t = MESSAGE_TEXT');
        self::assertInstanceOf(GetConditionDiagnosticsStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame('GET CURRENT DIAGNOSTICS CONDITION 1 @`t` = MESSAGE_TEXT', (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithAreaReadsAnotherArea(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('GET DIAGNOSTICS CONDITION 1 @t = MESSAGE_TEXT');
        self::assertInstanceOf(GetConditionDiagnosticsStatement::class, $statement);
        self::assertSame('GET STACKED DIAGNOSTICS CONDITION 1 @`t` = MESSAGE_TEXT', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withArea(DiagnosticsArea::Stacked)));
    }

    public function testWithConditionReadsAnotherNumber(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('GET DIAGNOSTICS CONDITION 1 @t = MESSAGE_TEXT');
        $other = $binder->bind('GET DIAGNOSTICS CONDITION 2 @t = MESSAGE_TEXT');
        self::assertInstanceOf(GetConditionDiagnosticsStatement::class, $statement);
        self::assertInstanceOf(GetConditionDiagnosticsStatement::class, $other);
        self::assertSame('GET CURRENT DIAGNOSTICS CONDITION 2 @`t` = MESSAGE_TEXT', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withCondition($other->condition)));
        self::assertSame('1', $statement->condition->spelling());
    }

    public function testWithItemsReplacesTheTargets(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('GET DIAGNOSTICS CONDITION 1 @t = MESSAGE_TEXT');
        self::assertInstanceOf(GetConditionDiagnosticsStatement::class, $statement);
        $changed = $statement->withItems([new ConditionDiagnostic('s', ConditionItem::ReturnedSqlState)]);
        self::assertSame('GET CURRENT DIAGNOSTICS CONDITION 1 @`s` = RETURNED_SQLSTATE', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testRejectsAnEmptyItemList(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('GET DIAGNOSTICS CONDITION 1 @t = MESSAGE_TEXT');
        self::assertInstanceOf(GetConditionDiagnosticsStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new GetConditionDiagnosticsStatement($statement->origin, DiagnosticsArea::Current, $statement->condition, []);
    }

    public function testRejectsAnotherDatabaseDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('GET DIAGNOSTICS CONDITION 1 @t = MESSAGE_TEXT');
        self::assertInstanceOf(GetConditionDiagnosticsStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new GetConditionDiagnosticsStatement(new Origin('s0', $statement->source, Dialect::PostgreSql), DiagnosticsArea::Current, $statement->condition, $statement->items);
    }
}
