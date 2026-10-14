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
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\Reset;

#[CoversClass(Reset::class)]
#[Medium]
final class ResetTest extends TestCase
{
    public function testDeriveStatementDerivesEveryItem(): void
    {
        $reset = (new Semantics(Dialect::MySql, 'mysql-8.2.0'))->analyze("RESET SLAVE FOR CHANNEL 'a\\nb', MASTER TO 1.5");

        self::assertEquals([new RefusedSetting(ReplicationError::LineFeed), new RefusedSetting(ReplicationError::FractionalNumber)], $reset->facts->diagnostics);
    }

    public function testRenderWritesTheItemsInOrder(): void
    {
        self::assertSame("RESET SLAVE FOR CHANNEL 'a\\nb', BINARY LOGS AND GTIDS TO 1.5", (new Semantics(Dialect::MySql, 'mysql-8.2.0'))->analyze("reset slave for channel 'a\\nb', master to 1.5")->toString());
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
        new Reset([new \SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetBinaryLogs(\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Legacy, new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('0')), new \SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetQueryCache()]);
    }
}
