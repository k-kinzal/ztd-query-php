<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Replica;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSetting;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\ReplicationError;
use SqlSemantics\Platform\MySql\Statement\Replication\Replica\StartReplica;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;
use SqlSemantics\Statement\Operation;

#[CoversClass(StartReplica::class)]
#[Medium]
final class StartReplicaTest extends TestCase
{
    public function testDeriveStatementReportsRefusedConditions(): void
    {
        $start = (new Semantics(Dialect::MySql))->analyze("START REPLICA SQL_THREAD UNTIL SOURCE_LOG_FILE = 'f' USER = 'u'");

        self::assertEquals([new RefusedSetting(ReplicationError::UntilCondition), new RefusedSetting(ReplicationError::ApplierWithCredentials)], $start->facts->diagnostics);
    }

    public function testDeriveStatementRejectsSlaveAfterItsRemoval(): void
    {
        $this->expectExceptionMessage('START SLAVE is not a statement of this release.');

        new Operation((new Semantics(Dialect::MySql, 'mysql-9.1.0'))->context([]), new StartReplica(Terminology::Legacy));
    }

    public function testRenderKeepsTheSpellingAndWritesEveryOption(): void
    {
        self::assertSame(
            "START SLAVE IO_THREAD, SQL_THREAD UNTIL SOURCE_LOG_FILE = 'f', SOURCE_LOG_POS = 4 USER = 'u' PASSWORD = 'p' DEFAULT_AUTH = 'a' PLUGIN_DIR = 'd' FOR CHANNEL 'c'",
            (new Semantics(Dialect::MySql, 'mysql-8.0.44'))->analyze("start slave io_thread, sql_thread until master_log_file = 'f', master_log_pos = 4 user = 'u' password = 'p' default_auth = 'a' plugin_dir = 'd' for channel 'c'")->toString(),
        );
    }
}
