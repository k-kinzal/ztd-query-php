<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\TableChange\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\TableChange\Alter\AlterRule;

#[CoversClass(AlterRule::class)]
#[Medium]
final class AlterRuleTest extends TestCase
{
    public function testStatementLowersEveryGeneration(): void
    {
        self::assertSame('ALTER IGNORE TABLE t FORCE', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('alter ignore table t force')->toString());
    }

    public function testCommandsFlattensModifiersItemsAndPartitioning(): void
    {
        self::assertSame('ALTER TABLE t LOCK = `NONE`, ADD COLUMN a INT, WITH VALIDATION PARTITION BY KEY ()', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t LOCK NONE, ADD a INT, WITH VALIDATION PARTITION BY KEY ()')->toString());
    }

    public function testOthersLowersRemovePartitioning(): void
    {
        self::assertSame('ALTER TABLE t FORCE REMOVE PARTITIONING', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('ALTER TABLE t FORCE REMOVE PARTITIONING')->toString());
    }

    public function testItemsKeepsRunsOfTableOptionsApart(): void
    {
        self::assertSame('ALTER TABLE t ENGINE InnoDB, ROW_FORMAT DYNAMIC', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t ENGINE = InnoDB, ROW_FORMAT = DYNAMIC')->toString());
    }
    public function testDefinitionRefusesAnOrdinaryRootForARetainedDeclaration(): void
    {
        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        (new Semantics(Dialect::MySql, '5.7.44'))->analyze('ALTER TABLE t MODIFY g INT AS(USER())');
    }

    public function testDefinitionRetainsPartitionOperandRangesWithoutRetainingInputText(): void
    {
        $sql = 'ALTER TABLE t WITH VALIDATION PARTITION BY HASH(USER())';
        try {
            (new Semantics(Dialect::MySql))->analyze($sql);
            self::fail('An inapplicable validation modifier retains a deferred alteration.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Alter\Problem\RefusedAlterTemplate::class, $failure->problem);
            $partition = $failure->problem->definition->commands[1];
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Alter\Partition\PartitionBy::class, $partition);
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Partition\Method\HashMethod::class, $partition->partitioning->method);
            $origin = $failure->sources->of($partition->partitioning->method->expression);
            self::assertSame(strpos($sql, 'USER()'), $origin?->offset);
            self::assertSame(6, $origin->length);
        }
    }
}
