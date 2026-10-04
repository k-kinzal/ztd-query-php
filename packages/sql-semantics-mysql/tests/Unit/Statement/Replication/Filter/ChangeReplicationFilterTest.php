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

        self::assertEquals([new RefusedSetting(ReplicationError::WildPattern)], $change->facts->diagnostics);
    }

    public function testRenderWritesTheChannel(): void
    {
        self::assertSame("CHANGE REPLICATION FILTER REPLICATE_DO_DB = (a) FOR CHANNEL 'c'", (new Semantics(Dialect::MySql, 'mysql-8.0.44'))->analyze("change replication filter replicate_do_db = (a) for channel 'c'")->toString());
    }
}
