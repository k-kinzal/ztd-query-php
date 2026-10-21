<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Replication;

use MySqlMemory\Command\Replication\UntilExecution;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Replica\ReplicaUntil;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;

#[CoversClass(UntilExecution::class)]
#[Small]
final class UntilExecutionTest extends TestCase
{
    public function testCheckRecordsTheNoteForAReachedCoordinate(): void
    {
        $session = (new Instance())->connect();
        $until = new ReplicaUntil(null, null, [new SourceOption(Terminology::Current, SourceOptionKind::LogFile, new Text('binlog.000001')), new SourceOption(Terminology::Current, SourceOptionKind::LogPosition, new Numeral('4'))]);
        (new UntilExecution())->check(null, $session);
        self::assertCount(0, $session->diagnostics->conditions);
        (new UntilExecution())->check($until, $session);
        self::assertCount(1, $session->diagnostics->conditions);
    }

    public function testCheckRefusesAReachedFilenameWithoutANumericExtension(): void
    {
        $until = new ReplicaUntil(null, null, [new SourceOption(Terminology::Current, SourceOptionKind::LogFile, new Text('binlog')), new SourceOption(Terminology::Current, SourceOptionKind::LogPosition, new Numeral('4'))]);
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1277);
        (new UntilExecution())->check($until, (new Instance())->connect());
    }

    public function testCheckDoesNotAttachALogCoordinateNoteToAGapCondition(): void
    {
        $session = (new Instance())->connect();
        (new UntilExecution())->check(new ReplicaUntil(\SqlSemantics\Platform\MySql\Statement\Replication\Replica\UntilPoint::AfterGaps), $session);
        self::assertSame([], $session->diagnostics->conditions);
    }
    public function testGtidsRetainsBothMalformedSetAndUntilDiagnostics(): void
    {
        $until = new ReplicaUntil(\SqlSemantics\Platform\MySql\Statement\Replication\Replica\UntilPoint::AfterGtids, new Text('bad'));
        $session = (new Instance('8.0.44'))->connect();
        $error = null;
        try {
            (new UntilExecution())->gtids($until, $session);
        } catch (SqlError $caught) {
            $error = $caught;
        }
        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame(1772, $error->getCode());
        self::assertSame([[1277, 'Incorrect parameter or combination of parameters for START REPLICA UNTIL']], $error->following);
        self::assertSame(1753, $session->diagnostics->conditions[0][1]);
    }

    public function testCompleteDistinguishesEmptyAfterAndBeforeSets(): void
    {
        $session = (new Instance())->connect();
        $execution = new UntilExecution();
        self::assertTrue($execution->complete(new ReplicaUntil(\SqlSemantics\Platform\MySql\Statement\Replication\Replica\UntilPoint::AfterGaps), $session));
        self::assertTrue($execution->complete(new ReplicaUntil(\SqlSemantics\Platform\MySql\Statement\Replication\Replica\UntilPoint::AfterGtids, new Text(' , ')), $session));
        self::assertFalse($execution->complete(new ReplicaUntil(\SqlSemantics\Platform\MySql\Statement\Replication\Replica\UntilPoint::BeforeGtids, new Text('')), $session));
    }

}
