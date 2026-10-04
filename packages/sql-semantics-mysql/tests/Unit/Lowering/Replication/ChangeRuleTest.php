<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Replication\ChangeRule;

#[CoversClass(ChangeRule::class)]
#[Medium]
final class ChangeRuleTest extends TestCase
{
    public function testStatementLowersEveryGeneration(): void
    {
        self::assertSame("CHANGE MASTER TO MASTER_HOST = 'h'", (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("change master to master_host = 'h'")->toString());
        self::assertSame("CHANGE REPLICATION SOURCE TO SOURCE_HOST = 'h'", (new Semantics(Dialect::MySql, 'mysql-8.0.44'))->analyze("change master to source_host = 'h'")->toString());
        self::assertSame("CHANGE REPLICATION SOURCE TO SOURCE_HOST = 'h' FOR CHANNEL 'c'", (new Semantics(Dialect::MySql, 'mysql-9.1.0'))->analyze("change replication source to source_host = 'h' for channel 'c'")->toString());
        self::assertSame('CHANGE REPLICATION FILTER REPLICATE_DO_DB = (a)', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('change replication filter replicate_do_db = (a)')->toString());
    }
}
