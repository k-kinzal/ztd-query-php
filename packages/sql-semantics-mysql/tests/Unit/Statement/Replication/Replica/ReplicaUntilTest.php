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
        self::assertSame("START REPLICA UNTIL SQL_AFTER_GTIDS = 'g', SOURCE_LOG_POS = 4", (new Semantics(Dialect::MySql))->analyze("start replica until sql_after_gtids = 'g', source_log_pos = 4")->toString());
    }

    public function testPositionsRejectAnOptionThatIsNotAPosition(): void
    {
        $this->expectExceptionMessage('UNTIL lists only log file and position options.');

        new ReplicaUntil(null, null, [new SourceOption(Terminology::Current, SourceOptionKind::Port, new Numeral('1'))]);
    }
}
