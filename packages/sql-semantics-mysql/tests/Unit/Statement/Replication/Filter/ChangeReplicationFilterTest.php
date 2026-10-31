<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Replication\Filter\ChangeReplicationFilter;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSetting;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\ReplicationError;

#[CoversClass(ChangeReplicationFilter::class)]
#[Medium]
final class ChangeReplicationFilterTest extends TestCase
{
    public function testDeriveStatementReportsAPatternWithoutDot(): void
    {
        $change = (new Semantics(Dialect::MySql))->analyze("CHANGE REPLICATION FILTER REPLICATE_WILD_DO_TABLE = ('a%', 'a.b')");

        self::assertEquals([new RefusedSetting(ReplicationError::WildPattern)], $change->diagnostics());
    }

    public function testRenderWritesTheChannel(): void
    {
        self::assertSame("CHANGE REPLICATION FILTER REPLICATE_DO_DB = (a) FOR CHANNEL 'c'", (new Semantics(Dialect::MySql, 'mysql-8.0.44'))->analyze("change replication filter replicate_do_db = (a) for channel 'c'")->toString());
    }

    public function testValueRejectsIncompatibleChannel(): void
    {
        $this->expectExceptionMessage('A replication channel is quoted text.');
        new ChangeReplicationFilter([new \SqlSemantics\Platform\MySql\Statement\Replication\Filter\ReplicationFilter(\SqlSemantics\Platform\MySql\Statement\Replication\Filter\FilterKind::DoDb, [])], new \SqlSemantics\Platform\MySql\Statement\Literal\Text('41', \SqlSemantics\Platform\MySql\Statement\Literal\Radix::Hexadecimal));
    }
}
