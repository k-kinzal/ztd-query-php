<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Replication;

use MySqlMemory\Command\Replication\LegacyReplication;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(LegacyReplication::class)]
#[Small]
final class LegacyReplicationTest extends TestCase
{
    public function testCheckRefusesTheReplicaWithoutAServerIdIn57(): void
    {
        $session = (new Instance('5.7.44', ['server_id' => '0']))->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1794);
        $this->expectExceptionMessage(LegacyReplication::UNCONFIGURED);

        (new LegacyReplication())->check($session->analyze('STOP SLAVE')->statement, $session);
    }

    public function testCheckWarnsOfResetQueryCacheIn57(): void
    {
        $session = (new Instance('5.7.44', ['server_id' => '0']))->connect();
        $session->query('RESET QUERY CACHE');

        self::assertSame([['Warning', 1681, "'RESET QUERY CACHE' is deprecated and will be removed in a future release."]], $session->diagnostics->conditions);
    }

    public function testCheckFailsAsResetMasterAfterResetSlaveWithoutABinaryLogInMySql56(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $answers = $session->run('RESET SLAVE, MASTER');
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(SqlError::class, $answers[0]);
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $warnings);
        self::assertSame([1186, ['1186', '1794']], [$answers[0]->getCode(), array_column($warnings->rows, 1)]);
    }
}
