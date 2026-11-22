<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Server\StatementRule;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(StatementRule::class)]
#[Medium]
final class StatementRuleTest extends TestCase
{
    public function testStatementHandsTheRuleToItsArea(): void
    {
        self::assertSame('RESTART', (new Inspection())->inspect('restart')->toString());
    }

    public function testDefinitionHandsAlterInstanceOf57ToItsArea(): void
    {
        self::assertSame('ALTER INSTANCE ROTATE innodb MASTER KEY', (new Inspection('mysql-5.7.44'))->inspect('alter instance rotate innodb master key')->toString());
    }

    public function testAreaLowersTablespacesAndLogfileGroups(): void
    {
        self::assertSame('DROP LOGFILE GROUP g', (new Inspection('mysql-5.6.51'))->inspect('drop logfile group g')->toString());
    }
}
