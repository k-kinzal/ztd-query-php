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
    public function testNameAcceptsTheIdentifierBoundary(): void
    {
        $platform = new \SqlSemantics\Platform\MySql\Platform();
        $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847);
        $lowering = new \SqlSemantics\Platform\MySql\Lowering\Lowering($platform->productions($profile), new \SqlSemantics\Lowering\Leaves(), $profile);
        $name = str_repeat('p', 64);
        $node = $platform->parser($profile)->parse('ALTER TABLE t ADD PARTITION (PARTITION ' . $name . ')')->find('part_definition')[0];
        self::assertSame($name, (new DefinitionRule($lowering))->name($lowering->form($node)->node(1))->value);
    }

    public function testCompletedKeepsAnOversizedNameBeforeListValidation(): void
    {
        $platform = new \SqlSemantics\Platform\MySql\Platform();
        $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847);
        $lowering = new \SqlSemantics\Platform\MySql\Lowering\Lowering($platform->productions($profile), new \SqlSemantics\Lowering\Leaves(), $profile);
        $name = str_repeat('p', 65);
        $node = $platform->parser($profile)->parse('ALTER TABLE t ADD PARTITION (PARTITION ' . $name . ')')->find('part_definition')[0];
        $notice = (new DefinitionRule($lowering))->completed($node);
        self::assertNotNull($notice);
        self::assertTrue($notice->aborts);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Name\Problem\NameTooLong::class, $notice->problem);
        self::assertSame($name, $notice->problem->name->value);
    }

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
