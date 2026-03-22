<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Spec\Probe;

use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Spec\Probe\PostgreSqlEngineProbe;
use Spec\Probe\ProbePhase;
use Spec\Support\TrackingPdo;

#[CoversClass(PostgreSqlEngineProbe::class)]
final class PostgreSqlEngineProbeTest extends TestCase
{
    public function testObserveRollsBackAcceptedStatementsToTheSavepoint(): void
    {
        $calls = [];
        $pdo = new TrackingPdo(function (string $sql) use (&$calls): int {
            $calls[] = $sql;

            return 0;
        });

        $probe = new PostgreSqlEngineProbe($pdo);
        $result = $probe->observe('CREATE TABLE t(i INT)');

        self::assertTrue($result->accepted);
        self::assertSame(ProbePhase::Execute, $result->phase);
        self::assertSame([
            'BEGIN',
            "SET SESSION statement_timeout = '500ms'",
            "SET SESSION lock_timeout = '500ms'",
            'SAVEPOINT probe_check',
            'CREATE TABLE t(i INT)',
            'ROLLBACK TO SAVEPOINT probe_check',
            'RELEASE SAVEPOINT probe_check',
        ], $calls);
    }

    public function testObserveRecoversTheSavepointAfterFailure(): void
    {
        $calls = [];
        $pdo = new TrackingPdo(function (string $sql) use (&$calls): int {
            $calls[] = $sql;
            if ($sql === 'BROKEN') {
                $exception = new PDOException('relation does not exist');
                $exception->errorInfo = ['42P01', 7];
                throw $exception;
            }

            return 0;
        });

        $probe = new PostgreSqlEngineProbe($pdo);
        $result = $probe->observe('BROKEN');

        self::assertFalse($result->accepted);
        self::assertSame(ProbePhase::Execute, $result->phase);
        self::assertSame('42P01', $result->sqlState);
        self::assertSame(7, $result->errorCode);
        self::assertSame([
            'BEGIN',
            "SET SESSION statement_timeout = '500ms'",
            "SET SESSION lock_timeout = '500ms'",
            'SAVEPOINT probe_check',
            'BROKEN',
            'ROLLBACK TO SAVEPOINT probe_check',
            'RELEASE SAVEPOINT probe_check',
        ], $calls);
    }
}
