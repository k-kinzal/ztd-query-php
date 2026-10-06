<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\TableChange\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\TableChange\Partition\DefinitionRule;

#[CoversClass(DefinitionRule::class)]
#[Medium]
final class DefinitionRuleTest extends TestCase
{
    public function testDefinitionsLowersTheList(): void
    {
        self::assertSame('ALTER TABLE t ADD PARTITION (PARTITION a, PARTITION b)', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t ADD PARTITION (PARTITION a, PARTITION b)')->toString());
    }

    public function testSpineAcceptsTheRowList(): void
    {
        self::assertSame('ALTER TABLE t ADD PARTITION (PARTITION a VALUES IN ((1), (2)))', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('ALTER TABLE t ADD PARTITION (PARTITION a VALUES IN ((1), (2)))')->toString());
    }

    public function testDefinitionLowersThe56Name(): void
    {
        self::assertSame('ALTER TABLE t ADD PARTITION (PARTITION a VALUES IN (1))', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('ALTER TABLE t ADD PARTITION (PARTITION a VALUES IN (1))')->toString());
    }

    public function testBoundLowersLessThan(): void
    {
        self::assertSame('ALTER TABLE t ADD PARTITION (PARTITION a VALUES LESS THAN (1))', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('ALTER TABLE t ADD PARTITION (PARTITION a VALUES LESS THAN (1))')->toString());
    }

    public function testLessThanLowersMaxvalue(): void
    {
        self::assertSame('ALTER TABLE t ADD PARTITION (PARTITION a VALUES LESS THAN MAXVALUE)', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t ADD PARTITION (PARTITION a VALUES LESS THAN MAXVALUE)')->toString());
    }

    public function testValuesInLowersRows(): void
    {
        self::assertSame('ALTER TABLE t ADD PARTITION (PARTITION a VALUES IN ((1, 2)))', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('ALTER TABLE t ADD PARTITION (PARTITION a VALUES IN ((1, 2)))')->toString());
    }

    public function testRowLowersTheItems(): void
    {
        self::assertSame('ALTER TABLE t ADD PARTITION (PARTITION a VALUES LESS THAN (1, MAXVALUE))', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t ADD PARTITION (PARTITION a VALUES LESS THAN (1, MAXVALUE))')->toString());
    }

    public function testItemLowersMaxvalue(): void
    {
        self::assertSame('ALTER TABLE t ADD PARTITION (PARTITION a VALUES LESS THAN (MAXVALUE))', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('ALTER TABLE t ADD PARTITION (PARTITION a VALUES LESS THAN (MAXVALUE))')->toString());
    }

    public function testSubpartitionsLowersTheList(): void
    {
        self::assertSame('ALTER TABLE t ADD PARTITION (PARTITION a (SUBPARTITION b, SUBPARTITION c))', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t ADD PARTITION (PARTITION a (SUBPARTITION b, SUBPARTITION c))')->toString());
    }

    public function testSubpartitionLowersAStringName(): void
    {
        self::assertSame('ALTER TABLE t ADD PARTITION (PARTITION a (SUBPARTITION s))', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("ALTER TABLE t ADD PARTITION (PARTITION a (SUBPARTITION 's'))")->toString());
    }
}
