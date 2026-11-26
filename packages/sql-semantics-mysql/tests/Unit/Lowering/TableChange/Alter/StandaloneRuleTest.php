<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\TableChange\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\TableChange\Alter\StandaloneRule;
use SqlSemantics\Platform\MySql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Model\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(StandaloneRule::class)]
#[Medium]
final class StandaloneRuleTest extends TestCase
{
    public function testClaimsAnswersWhetherAProductionIsAnOperation(): void
    {
        $rule = new StandaloneRule(new Lowering((new Platform())->productions((new Platform())->profile(null, null, ParameterStyle::Native)), new Leaves(), (new Platform())->profile(null, null, ParameterStyle::Native)));

        self::assertTrue($rule->claims('standalone_alter_commands: SECONDARY_LOAD_SYM'));
        self::assertFalse($rule->claims('alter_commands:'));
    }

    public function testCommandLowersEveryOperation(): void
    {
        self::assertSame('ALTER TABLE t DISCARD TABLESPACE', (new Inspection('mysql-5.6.51'))->inspect('ALTER TABLE t DISCARD TABLESPACE')->toString());
    }

    public function testPairLowersTheOperationsWithTwoOperands(): void
    {
        self::assertSame('ALTER TABLE t COALESCE PARTITION NO_WRITE_TO_BINLOG 2', (new Inspection())->inspect('ALTER TABLE t COALESCE PARTITION LOCAL 2')->toString());
    }

    public function testMaintainLowersTheOperationOnPartitions(): void
    {
        self::assertSame('ALTER TABLE t REBUILD PARTITION NO_WRITE_TO_BINLOG ALL', (new Inspection())->inspect('ALTER TABLE t REBUILD PARTITION LOCAL ALL')->toString());
    }

    public function testLocalLowersNoWriteToBinlog(): void
    {
        self::assertSame('ALTER TABLE t ANALYZE PARTITION NO_WRITE_TO_BINLOG p', (new Inspection())->inspect('ALTER TABLE t ANALYZE PARTITION NO_WRITE_TO_BINLOG p')->toString());
    }

    public function testAddRuleLowersThe5xForms(): void
    {
        self::assertSame('ALTER TABLE t ADD PARTITION (PARTITION p VALUES IN (1))', (new Inspection('mysql-5.6.51'))->inspect('ALTER TABLE t ADD PARTITION (PARTITION p VALUES IN (1))')->toString());
    }

    public function testReorganizeRuleLowersThe5xForms(): void
    {
        self::assertSame('ALTER TABLE t REORGANIZE PARTITION a, b INTO (PARTITION c)', (new Inspection('mysql-5.7.44'))->inspect('ALTER TABLE t REORGANIZE PARTITION a, b INTO (PARTITION c)')->toString());
    }

    public function testExchangeLowersThePartitionAndTheTable(): void
    {
        self::assertSame('ALTER TABLE t EXCHANGE PARTITION p WITH TABLE u', (new Inspection('mysql-5.6.51'))->inspect('ALTER TABLE t EXCHANGE PARTITION p WITH TABLE u')->toString());
    }

    public function testSelectionLowersAll(): void
    {
        self::assertSame('ALTER TABLE t TRUNCATE PARTITION ALL', (new Inspection('mysql-5.7.44'))->inspect('ALTER TABLE t TRUNCATE PARTITION ALL')->toString());
    }

    public function testNamesLowersTheNames(): void
    {
        self::assertSame('ALTER TABLE t DROP PARTITION a, b', (new Inspection('mysql-5.6.51'))->inspect('ALTER TABLE t DROP PARTITION a, b')->toString());
    }

    public function testItemLowersOneName(): void
    {
        self::assertSame('ALTER TABLE t DROP PARTITION a', (new Inspection('mysql-5.7.44'))->inspect('ALTER TABLE t DROP PARTITION a')->toString());
    }
}
