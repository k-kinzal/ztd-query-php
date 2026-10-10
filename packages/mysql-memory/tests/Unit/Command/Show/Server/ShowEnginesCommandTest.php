<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Show\Server;

use MySqlMemory\Command\Show\Heading;
use MySqlMemory\Command\Show\Server\ShowEnginesCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
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
