<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Inspection\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Query\Inspection\Engine\EngineReport;
use SqlSemantics\Model\Query\Inspection\Engine\EngineSelection;
use SqlSemantics\Model\Statement\Inspection\Server\ShowEngineReportStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ShowEngineReportStatement::class)]
#[Medium]
final class ShowEngineReportStatementTest extends TestCase
{
    public function testResultColumnsFollowTheServerLayout(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SHOW ENGINE 'InnoDB' STATUS");
        self::assertInstanceOf(ShowEngineReportStatement::class, $statement);
        self::assertSame('InnoDB', $statement->engine);
        self::assertSame(EngineReport::Status, $statement->report);
        self::assertSame(['Type', 'Name', 'Status'], array_column($statement->resultColumns(), 'name'));
        self::assertSame('SHOW ENGINE `InnoDB` STATUS', $statement->toString());
    }

    public function testWithEngineAsksEveryEngineImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW ENGINE INNODB MUTEX');
        self::assertInstanceOf(ShowEngineReportStatement::class, $statement);
        $changed = $statement->withEngine(EngineSelection::All);
        self::assertNotSame($statement, $changed);
        self::assertSame('INNODB', $statement->engine);
        self::assertSame(EngineSelection::All, $changed->engine);
        self::assertSame('SHOW ENGINE ALL MUTEX', $changed->toString());
        self::assertSame('SHOW ENGINE `ndb` MUTEX', $changed->withEngine('ndb')->toString());
    }

    public function testWithReportAsksAnotherReportImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW ENGINE INNODB STATUS');
        self::assertInstanceOf(ShowEngineReportStatement::class, $statement);
        $changed = $statement->withReport(EngineReport::Logs);
        self::assertNotSame($statement, $changed);
        self::assertSame(EngineReport::Status, $statement->report);
        self::assertSame('SHOW ENGINE `INNODB` LOGS', $changed->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW ENGINE ALL LOGS');
        self::assertInstanceOf(ShowEngineReportStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame([$statement->engine, $statement->report], [$copy->engine, $copy->report]);
    }

    public function testRejectsAnEmptyEngineName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW ENGINE ALL LOGS');
        self::assertInstanceOf(ShowEngineReportStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new ShowEngineReportStatement($statement->origin, '', EngineReport::Logs);
    }
}
