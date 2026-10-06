<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Reset;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSetting;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\ReplicationError;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetBinaryLogs;

#[CoversClass(ResetBinaryLogs::class)]
#[Medium]
final class ResetBinaryLogsTest extends TestCase
{
    public function testDeriveTargetReportsAFileNumberOutOfRange(): void
    {
        $reset = (new Semantics(Dialect::MySql))->analyze('RESET BINARY LOGS AND GTIDS TO 0, BINARY LOGS AND GTIDS TO 2000000001, BINARY LOGS AND GTIDS TO 2000000000');

        self::assertEquals([new RefusedSetting(ReplicationError::FileNumberOutOfRange), new RefusedSetting(ReplicationError::FileNumberOutOfRange)], $reset->facts->diagnostics);
    }

    public function testRenderKeepsMasterBeforeTheCurrentSpellingExists(): void
    {
        self::assertSame('RESET MASTER TO 7', (new Semantics(Dialect::MySql, 'mysql-8.1.0'))->analyze('reset master to 7')->toString());
    }
}
