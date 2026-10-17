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
}
