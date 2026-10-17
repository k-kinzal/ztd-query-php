<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Reset;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\Reset;

#[CoversClass(Reset::class)]
#[Medium]
final class ResetTest extends TestCase
{
    public function testDeriveStatementDerivesEveryItem(): void
    {
        $reset = (new Semantics(Dialect::MySql, 'mysql-8.2.0'))->analyze("RESET SLAVE FOR CHANNEL 'channel', MASTER TO 7");

        self::assertSame([], $reset->facts->diagnostics);
    }

    public function testRenderWritesTheItemsInOrder(): void
    {
        self::assertSame("RESET SLAVE FOR CHANNEL 'channel', MASTER TO 7", (new Semantics(Dialect::MySql, 'mysql-8.2.0'))->analyze("reset slave for channel 'channel', master to 7")->toString());
    }

    public function testTargetsRejectMixedVocabulariesOfTheSameKind(): void
    {
        $this->expectExceptionMessage('Repeated RESET targets use one vocabulary for each target kind.');
        new Reset([new \SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetBinaryLogs(\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Legacy), new \SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetBinaryLogs(\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Current)]);
    }

    public function testTargetsAllowDifferentKindsWithDifferentRenameDates(): void
    {
        $reset = new Reset([new \SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetBinaryLogs(\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Legacy), new \SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetReplica(\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Current)]);

        self::assertCount(2, $reset->targets);
    }


    public function testValueRejectsIncompatibleGeneration(): void
    {
        $this->expectExceptionMessage('A query cannot combine features removed before MySQL 8.0 with features introduced in MySQL 8.0 or later.');
        new Reset([new \SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetBinaryLogs(\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Legacy, new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('1')), new \SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetQueryCache()]);
    }
    public function testEffectiveTargetsKeepsTheLastExplicitIndexAndLastReplicaOptions(): void
    {
        $logs = new \SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetBinaryLogs(\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Current, new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('7'));
        $replica = new \SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetReplica(\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Current);
        $reset = new Reset([new \SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetReplica(\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Current, true, new \SqlSemantics\Platform\MySql\Statement\Literal\Text('missing')), $logs, new \SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetBinaryLogs(\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Current), $replica]);

        self::assertSame([$logs, $replica], $reset->effectiveTargets());
        self::assertCount(4, $reset->targets);
    }

    public function testDeriveStatementRetainsWrittenNoticeOrderWithoutSourceOrigins(): void
    {
        $derivation = new \SqlSemantics\Construction\Derivation((new Semantics(Dialect::MySql, 'mysql-8.2.0'))->context());
        $reset = new Reset([
            new \SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetReplica(\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Current, synonym: true),
            new \SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetBinaryLogs(\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Current),
            new \SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetReplica(\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Current),
            new \SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetBinaryLogs(\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Current, synonym: true),
        ]);
        $reset->deriveStatement($derivation);

        self::assertSame([
            \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::ReplicationSpelling,
            \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::ResetMaster,
            \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::ResetMaster,
        ], array_column($derivation->facts()->warnings, 'construct'));
    }

}
