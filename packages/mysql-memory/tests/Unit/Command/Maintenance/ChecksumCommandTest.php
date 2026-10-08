<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Maintenance;

use MySqlMemory\Command\Maintenance\ChecksumCommand;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChecksumCommand::class)]
#[Small]
final class ChecksumCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new ChecksumCommand())->clearsDiagnostics());
    }

    public function testExecuteAnswersNullWithAnErrorForATableThatDoesNotExist(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');

        $result = $session->query('CHECKSUM TABLE t, nope, abc.q')[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['d.t', '0'], ['d.nope', null], ['abc.q', null]], $result->rows);
        self::assertSame([['Checksum', 22, 32896]], array_map(static fn ($column): array => [$column->name, $column->length, $column->flags], array_slice($result->columns, 1)));
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Error', '1146', "Table 'd.nope' doesn't exist"], ['Error', '1049', "Unknown database 'abc'"]], $warnings->rows);
    }

    public function testExecuteAnswersNullForQuick(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');

        $result = $session->query('CHECKSUM TABLE t QUICK')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['d.t', null]], $result->rows);
    }

    public function testChecksumAnswersZeroForNoRows(): void
    {
        self::assertSame('0', (new ChecksumCommand())->checksum([]));
    }
}
