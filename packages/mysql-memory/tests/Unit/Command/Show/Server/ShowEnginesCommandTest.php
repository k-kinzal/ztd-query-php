<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Show\Server;

use MySqlMemory\Command\Show\Heading;
use MySqlMemory\Command\Show\Server\ShowEnginesCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShowEnginesCommand::class)]
#[Small]
final class ShowEnginesCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new ShowEnginesCommand())->clearsDiagnostics());
    }

    public function testExecuteListsTheEngines(): void
    {
        $result = (new Instance())->connect()->query('SHOW STORAGE ENGINES')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertContains(['InnoDB', 'DEFAULT', 'Supports transactions, row-level locking, and foreign keys', 'YES', 'YES', 'YES'], $result->rows);
        self::assertContains(['ndbcluster', 'NO', 'Clustered, fault-tolerant tables', null, null, null], $result->rows);
    }

    public function testExecuteReportsNothingOfAnEngine(): void
    {
        $result = (new Instance())->connect()->query('SHOW ENGINE ALL LOGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([], $result->rows);
        self::assertSame(['Type', 40, 31], [$result->columns[0]->name, $result->columns[0]->length, $result->columns[0]->decimals]);
    }

    public function testExecuteReturnsInnoDbMonitorSections(): void
    {
        $result = (new Instance())->connect()->query('SHOW ENGINE INNOBASE STATUS')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertCount(1, $result->rows);
        self::assertSame(['InnoDB', ''], array_slice($result->rows[0], 0, 2));
        self::assertIsString($result->rows[0][2]);
        self::assertStringContainsString("\nTRANSACTIONS\n", $result->rows[0][2]);
        self::assertStringContainsString("\nROW OPERATIONS\n", $result->rows[0][2]);
        self::assertStringContainsString('END OF INNODB MONITOR OUTPUT', $result->rows[0][2]);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerInstrumentationCatalogs(): iterable
    {
        yield '5.6' => ['5.6.51', 167];
        yield '5.7' => ['5.7.44', 229];
        yield '8.0' => ['8.0.44', 248];
        yield '8.1' => ['8.1.0', 248];
        yield '8.2' => ['8.2.0', 254];
        yield '8.3' => ['8.3.0', 254];
        yield '8.4' => ['8.4.7', 254];
        yield '9.0' => ['9.0.1', 254];
        yield '9.1' => ['9.1.0', 257];
    }

    #[DataProvider('providerInstrumentationCatalogs')]
    public function testExecuteListsReleaseSpecificPerformanceSchemaMetrics(string $version, int $metrics): void
    {
        $result = (new Instance($version))->connect()->query('SHOW ENGINE PERFORMANCE_SCHEMA STATUS')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertCount($metrics, $result->rows);
        self::assertContains(['performance_schema', 'performance_schema.memory', '0'], $result->rows);
        self::assertContains(['performance_schema', $version === '5.6.51' ? 'events_waits_current.row_count' : 'events_waits_current.count', '0'], $result->rows);
        self::assertCount($metrics, array_unique(array_column($result->rows, 1)));
    }

    public function testExecuteRefusesAnEngineTheServerDoesNotHave(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1286);
        $this->expectExceptionMessage("Unknown storage engine 'FEDERATED'");

        (new Instance())->connect()->query('SHOW ENGINE FEDERATED MUTEX');
    }

    public function testCatalogHeadingsReadInformationSchemaEngines(): void
    {
        self::assertSame(['ENGINE', 'SUPPORT', 'COMMENT', 'TRANSACTIONS', 'XA', 'SAVEPOINTS'], array_map(static fn (Heading $heading): string => $heading->originalName, (new ShowEnginesCommand())->catalogHeadings()));
    }
}
