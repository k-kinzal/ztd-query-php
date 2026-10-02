<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Replication\ReplicationRules;
use SqlSemantics\Platform\MySql\Platform;

#[CoversClass(ReplicationRules::class)]
#[Small]
final class ReplicationRulesTest extends TestCase
{
    public function testStatementReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new ReplicationRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL replication family: statement');

        $rules->statement(new Node('rule', 0, []));
    }

    public function testChannelReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new ReplicationRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL replication family: channel');

        $rules->channel(new Node('rule', 0, []));
    }
}
