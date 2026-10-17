<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Replica;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Replication\Replica\ReplicaUntil;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;

#[CoversClass(ReplicaUntil::class)]
#[Medium]
final class ReplicaUntilTest extends TestCase
{
    public function testRenderWritesTheConditionsInOrder(): void
    {
        self::assertSame("START REPLICA UNTIL SOURCE_LOG_POS = 4, SOURCE_LOG_FILE = 'f.1'", (new Semantics(Dialect::MySql))->analyze("start replica until source_log_pos = 4, source_log_file = 'f.1'")->toString());
    }

    public function testPositionsRejectAnOptionThatIsNotAPosition(): void
    {
        $this->expectExceptionMessage('UNTIL lists only log file and position options.');

        new ReplicaUntil(null, null, [new SourceOption(Terminology::Current, SourceOptionKind::Port, new Numeral('1'))]);
    }

    public function testValueRejectsIncompatibleGtids(): void
    {
        $this->expectExceptionMessage('A GTID set is quoted text.');
        new ReplicaUntil(\SqlSemantics\Platform\MySql\Statement\Replication\Replica\UntilPoint::BeforeGtids, new \SqlSemantics\Platform\MySql\Statement\Literal\Text('41', \SqlSemantics\Platform\MySql\Statement\Literal\Radix::Hexadecimal));
    }
}
