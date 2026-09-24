<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Engine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Query\Inspection\Engine\EngineReport;
use SqlSemantics\Model\Statement\Inspection\Server\ShowEngineReportStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(EngineReport::class)]
#[Medium]
final class EngineReportTest extends TestCase
{
    #[TestWith(['SHOW ENGINE INNODB STATUS', EngineReport::Status])]
    #[TestWith(['SHOW ENGINE INNODB MUTEX', EngineReport::Mutex])]
    #[TestWith(['SHOW ENGINE PERFORMANCE_SCHEMA LOGS', EngineReport::Logs])]
    public function testRetainsTheRequestedReport(string $sql, EngineReport $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql);
        self::assertInstanceOf(ShowEngineReportStatement::class, $statement);
        self::assertSame($expected, $statement->report);
        self::assertSame($expected->value, strtoupper(substr($sql, -strlen($expected->value))));
    }
}
