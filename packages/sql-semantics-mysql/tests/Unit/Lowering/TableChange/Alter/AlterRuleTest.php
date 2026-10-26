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
        self::assertSame('ALTER TABLE t LOCK = NONE, ADD a INT, WITH VALIDATION PARTITION BY KEY ()', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t LOCK NONE, ADD a INT, WITH VALIDATION PARTITION BY KEY ()')->toString());
    }

    public function testOthersLowersRemovePartitioning(): void
    {
        self::assertSame('ALTER TABLE t FORCE REMOVE PARTITIONING', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('ALTER TABLE t FORCE REMOVE PARTITIONING')->toString());
    }

    public function testItemsKeepsRunsOfTableOptionsApart(): void
    {
        self::assertSame('ALTER TABLE t ENGINE InnoDB, ROW_FORMAT DYNAMIC', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t ENGINE = InnoDB, ROW_FORMAT = DYNAMIC')->toString());
    }
    public function testDefinitionPublishesAnOrdinaryRootWithItsInvocationCondition(): void
    {
        $operation = (new Semantics(Dialect::MySql, '5.7.44'))->analyze('ALTER TABLE t MODIFY g INT AS(USER())');
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $operation);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Alter\AlterTable::class, $operation->statement);
        self::assertTrue($operation->statement->hasExecutionCondition());
    }

    public function testDefinitionRetainsPartitionOperandRangesWithoutRetainingInputText(): void
    {
        $sql = 'ALTER TABLE t WITH VALIDATION PARTITION BY HASH(VERSION())';
        $operation = (new Semantics(Dialect::MySql))->analyze($sql);
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $operation);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Alter\AlterTable::class, $operation->statement);
        $partition = $operation->statement->commands[1];
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Alter\Partition\PartitionBy::class, $partition);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Partition\Method\HashMethod::class, $partition->partitioning->method);
        $origin = $operation->sources->of($partition->partitioning->method->expression);
        self::assertSame(strpos($sql, 'VERSION()'), $origin?->offset);
        self::assertSame(9, $origin->length);
    }
}
