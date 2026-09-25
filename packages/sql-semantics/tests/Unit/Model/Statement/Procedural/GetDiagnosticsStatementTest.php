<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Procedural;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Condition\DiagnosticsArea;
use SqlSemantics\Model\Configuration\Condition\StatementDiagnostic;
use SqlSemantics\Model\Configuration\Condition\StatementItem;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Procedural\GetDiagnosticsStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(GetDiagnosticsStatement::class)]
#[Medium]
final class GetDiagnosticsStatementTest extends TestCase
{
    public function testWithOriginRetainsAreaAndItems(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('GET STACKED DIAGNOSTICS @n = NUMBER');
        self::assertInstanceOf(GetDiagnosticsStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame('GET STACKED DIAGNOSTICS @`n` = NUMBER', (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithAreaReadsAnotherArea(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('GET DIAGNOSTICS @n = NUMBER');
        self::assertInstanceOf(GetDiagnosticsStatement::class, $statement);
        self::assertSame(DiagnosticsArea::Stacked, $statement->withArea(DiagnosticsArea::Stacked)->area);
        self::assertSame(DiagnosticsArea::Current, $statement->area);
    }

    public function testWithItemsReplacesTheTargets(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('GET DIAGNOSTICS @n = NUMBER');
        self::assertInstanceOf(GetDiagnosticsStatement::class, $statement);
        $changed = $statement->withItems([new StatementDiagnostic('r', StatementItem::RowCount), new StatementDiagnostic('n', StatementItem::Number)]);
        self::assertSame('GET CURRENT DIAGNOSTICS @`r` = ROW_COUNT, @`n` = NUMBER', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testRejectsAnEmptyItemList(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('GET DIAGNOSTICS @n = NUMBER');
        $this->expectException(InvalidStructure::class);
        new GetDiagnosticsStatement($statement->origin, DiagnosticsArea::Current, []);
    }

    public function testRejectsAnotherDatabaseDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('GET DIAGNOSTICS @n = NUMBER');
        $this->expectException(InvalidStructure::class);
        new GetDiagnosticsStatement(new Origin('s0', $statement->source, Dialect::PostgreSql), DiagnosticsArea::Current, [new StatementDiagnostic('n', StatementItem::Number)]);
    }
}
