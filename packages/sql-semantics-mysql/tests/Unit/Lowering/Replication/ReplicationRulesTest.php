<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Replication\ReplicationRules;
use SqlSemantics\Platform\MySql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Model\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ReplicationRules::class)]
#[Medium]
final class ReplicationRulesTest extends TestCase
{
    public function testStatementHandsEveryStatementToItsRule(): void
    {
        self::assertSame('BINLOG \'x\'', (new Inspection())->inspect("binlog 'x'")->toString());
        self::assertSame('STOP GROUP_REPLICATION', (new Inspection())->inspect('stop group_replication')->toString());
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
        self::assertSame("STOP REPLICA FOR CHANNEL 'c'", (new Inspection())->inspect("stop replica for channel 'c'")->toString());
    }

    public function testTerminologyAnswersTheWrittenSpelling(): void
    {
        self::assertSame('STOP SLAVE', (new Inspection('mysql-8.3.0'))->inspect('stop slave')->toString());
        self::assertSame('STOP REPLICA', (new Inspection('mysql-8.3.0'))->inspect('stop replica')->toString());
    }
}
