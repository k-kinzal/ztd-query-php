<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\TableChange\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\TableChange\Alter\AlterRule;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(AlterRule::class)]
#[Medium]
final class AlterRuleTest extends TestCase
{
    public function testStatementLowersEveryGeneration(): void
    {
        self::assertSame('ALTER IGNORE TABLE t FORCE', (new Inspection('mysql-5.6.51'))->inspect('alter ignore table t force')->toString());
    }

    public function testCommandsFlattensModifiersItemsAndPartitioning(): void
    {
        self::assertSame('ALTER TABLE t LOCK = `NONE`, ADD COLUMN a INT, WITH VALIDATION PARTITION BY KEY ()', (new Inspection())->inspect('ALTER TABLE t LOCK NONE, ADD a INT, WITH VALIDATION PARTITION BY KEY ()')->toString());
    }

    public function testOthersLowersRemovePartitioning(): void
    {
        self::assertSame('ALTER TABLE t FORCE REMOVE PARTITIONING', (new Inspection('mysql-5.7.44'))->inspect('ALTER TABLE t FORCE REMOVE PARTITIONING')->toString());
    }

    public function testItemsKeepsRunsOfTableOptionsApart(): void
    {
        self::assertSame('ALTER TABLE t ENGINE InnoDB, ROW_FORMAT DYNAMIC', (new Inspection())->inspect('ALTER TABLE t ENGINE = InnoDB, ROW_FORMAT = DYNAMIC')->toString());
    }
}
