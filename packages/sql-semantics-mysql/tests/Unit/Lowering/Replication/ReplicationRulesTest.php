<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Replication\ReplicationRules;
use SqlSemantics\Platform\MySql\Platform;

#[CoversClass(ReplicationRules::class)]
#[Medium]
final class ReplicationRulesTest extends TestCase
{
    public function testStatementHandsEveryStatementToItsRule(): void
    {
        self::assertSame('BINLOG \'x\'', (new Semantics(Dialect::MySql))->analyze("binlog 'x'")->toString());
        self::assertSame('STOP GROUP_REPLICATION', (new Semantics(Dialect::MySql))->analyze('stop group_replication')->toString());
    }

    public function testStatementReportsANodeOfAnotherRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new ReplicationRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('The grammar release has no production rule#0.');

        $rules->statement(new Node('rule', 0, []));
    }

    public function testChannelLowersTheName(): void
    {
        self::assertSame("STOP REPLICA FOR CHANNEL 'c'", (new Semantics(Dialect::MySql))->analyze("stop replica for channel 'c'")->toString());
    }

    public function testTerminologyAnswersTheWrittenSpelling(): void
    {
        self::assertSame('STOP SLAVE', (new Semantics(Dialect::MySql, 'mysql-8.3.0'))->analyze('stop slave')->toString());
        self::assertSame('STOP REPLICA', (new Semantics(Dialect::MySql, 'mysql-8.3.0'))->analyze('stop replica')->toString());
    }
}
