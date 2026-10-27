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

#[CoversClass(StartReplica::class)]
#[Medium]
final class StartReplicaTest extends TestCase
{
    public function testDeriveStatementReportsRefusedConditions(): void
    {
        $start = (new Semantics(Dialect::MySql))->analyze("START REPLICA SQL_THREAD UNTIL SOURCE_LOG_FILE = 'f', SOURCE_LOG_POS = 4 USER = 'u'");

        self::assertEquals([new RefusedSetting(ReplicationError::ApplierWithCredentials)], $start->diagnostics());
    }

    public function testDeriveStatementRejectsSlaveAfterItsRemoval(): void
    {
        $this->expectExceptionMessage('START SLAVE is not a statement of this release.');

        (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish((new Semantics(Dialect::MySql, 'mysql-9.1.0'))->context([]), new StartReplica(Terminology::Legacy), new \SqlSemantics\Statement\Source\SourceMap());
    }

    public function testRenderKeepsTheSpellingAndWritesEveryOption(): void
    {
        self::assertSame(
            "START SLAVE IO_THREAD, SQL_THREAD UNTIL MASTER_LOG_FILE = 'f', MASTER_LOG_POS = 4 USER = 'u' PASSWORD = 'p' DEFAULT_AUTH = 'a' PLUGIN_DIR = 'd' FOR CHANNEL 'c'",
            (new Semantics(Dialect::MySql, 'mysql-8.0.44'))->analyze("start slave io_thread, sql_thread until master_log_file = 'f', master_log_pos = 4 user = 'u' password = 'p' default_auth = 'a' plugin_dir = 'd' for channel 'c'")->toString(),
        );
    }

    public function testValueRejectsIncompatibleUser(): void
    {
        $this->expectExceptionMessage('Replication credentials and channel names are quoted text.');
        new StartReplica(Terminology::Legacy, user: new \SqlSemantics\Platform\MySql\Statement\Literal\Text('41', \SqlSemantics\Platform\MySql\Statement\Literal\Radix::Hexadecimal));
    }

    public function testValueRejectsIncompatiblePassword(): void
    {
        $this->expectExceptionMessage('Replication credentials and channel names are quoted text.');
        new StartReplica(Terminology::Legacy, password: new \SqlSemantics\Platform\MySql\Statement\Literal\Text('41', \SqlSemantics\Platform\MySql\Statement\Literal\Radix::Hexadecimal));
    }

    public function testValueRejectsIncompatibleDefaultauth(): void
    {
        $this->expectExceptionMessage('Replication credentials and channel names are quoted text.');
        new StartReplica(Terminology::Legacy, defaultAuth: new \SqlSemantics\Platform\MySql\Statement\Literal\Text('41', \SqlSemantics\Platform\MySql\Statement\Literal\Radix::Hexadecimal));
    }

    public function testValueRejectsIncompatiblePlugindir(): void
    {
        $this->expectExceptionMessage('Replication credentials and channel names are quoted text.');
        new StartReplica(Terminology::Legacy, pluginDir: new \SqlSemantics\Platform\MySql\Statement\Literal\Text('41', \SqlSemantics\Platform\MySql\Statement\Literal\Radix::Hexadecimal));
    }

    public function testValueRejectsIncompatibleChannel(): void
    {
        $this->expectExceptionMessage('Replication credentials and channel names are quoted text.');
        new StartReplica(Terminology::Legacy, channel: new \SqlSemantics\Platform\MySql\Statement\Literal\Text('41', \SqlSemantics\Platform\MySql\Statement\Literal\Radix::Hexadecimal));
    }
}
