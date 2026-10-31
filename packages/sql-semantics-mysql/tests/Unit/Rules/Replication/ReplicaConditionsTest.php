<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Replication\ReplicaConditions;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\ReplicationError;
use SqlSemantics\Platform\MySql\Statement\Replication\Replica\ReplicaThread;
use SqlSemantics\Platform\MySql\Statement\Replication\Replica\ReplicaUntil;
use SqlSemantics\Platform\MySql\Statement\Replication\Replica\UntilPoint;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;

#[CoversClass(ReplicaConditions::class)]
#[Small]
final class ReplicaConditionsTest extends TestCase
{
    public function testUntilAcceptsCompletePositionsAndLoneConditions(): void
    {
        $file = new SourceOption(Terminology::Current, SourceOptionKind::RelayLogFile, new Text('r'));
        $position = new SourceOption(Terminology::Current, SourceOptionKind::RelayLogPosition, new Numeral('4'));
        $conditions = new ReplicaConditions();

        self::assertNull($conditions->until(new ReplicaUntil(null, null, [$file, $position])));
        self::assertNull($conditions->until(new ReplicaUntil(UntilPoint::AfterGaps)));

    }

    public function testCredentialsRefusedNeedsTheApplierAlone(): void
    {
        $conditions = new ReplicaConditions();

        self::assertTrue($conditions->credentialsRefused([ReplicaThread::Applier], true));
        self::assertFalse($conditions->credentialsRefused([ReplicaThread::Applier, ReplicaThread::Receiver], true));
        self::assertFalse($conditions->credentialsRefused([ReplicaThread::Applier, ReplicaThread::Relay], true));
        self::assertFalse($conditions->credentialsRefused([ReplicaThread::Relay], true));
        self::assertFalse($conditions->credentialsRefused([], true));
        self::assertFalse($conditions->credentialsRefused([ReplicaThread::Applier], false));
    }

    public function testCombinationRejectsMixedConditionsBeforeConstruction(): void
    {
        $conditions = new ReplicaConditions();
        $file = new SourceOption(Terminology::Current, SourceOptionKind::LogFile, new Text('f'));
        $position = new SourceOption(Terminology::Current, SourceOptionKind::LogPosition, new Numeral('4'));
        self::assertSame(ReplicationError::UntilCondition, $conditions->combination(null, null, [$file]));
        self::assertSame(ReplicationError::UntilCondition, $conditions->combination(UntilPoint::BeforeGtids, new Text('g'), [$position]));
        self::assertSame(ReplicationError::UntilCondition, $conditions->combination(UntilPoint::AfterGaps, null, [$file]));
        self::assertNull($conditions->combination(null, null, [$position, $file]));
    }
}
