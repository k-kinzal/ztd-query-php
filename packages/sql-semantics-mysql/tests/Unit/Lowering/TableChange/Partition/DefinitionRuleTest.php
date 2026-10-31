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
    #[\PHPUnit\Framework\Attributes\DataProvider('providerBoundConditions')]
    public function testDefinitionsRetainCompleteBounds(string $release, string $sql): void
    {
        $statement = (new Semantics(Dialect::MySql, $release))->analyze($sql);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Alter\AlterTable::class, $statement);
        self::assertSame($sql, $statement->toString());
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $statement->warnings()[0]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Partition\Problem\DefinitionIssue::class, $statement->warnings()[0]->problem);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerBoundConditions(): iterable
    {
        foreach (\SqlSemantics\Contract\GrammarRelease::cases() as $release) {
            if ($release->database() !== 'mysql') {
                continue;
            }
            foreach ([
                'RANGE (i) (PARTITION p)',
                'LIST (i) (PARTITION p)',
                'HASH (i) (PARTITION p VALUES IN (1))',
                'LIST (i) (PARTITION p VALUES LESS THAN (1))',
                'RANGE (i) (PARTITION p VALUES LESS THAN (1), PARTITION q)',
            ] as $index => $clause) {
                yield $release->value . ' ' . $index => [$release->value, 'ALTER TABLE t PARTITION BY ' . $clause];
            }
        }
    }

    public function testNameAcceptsTheIdentifierBoundary(): void
    {
        $platform = new \SqlSemantics\Platform\MySql\Platform();
        $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847);
        $lowering = new \SqlSemantics\Platform\MySql\Lowering\Lowering($platform->productions($profile), new \SqlSemantics\Lowering\Leaves(), $profile);
        $name = str_repeat('p', 64);
        $node = $platform->parser($profile)->parse('ALTER TABLE t ADD PARTITION (PARTITION ' . $name . ')')->find('part_definition')[0];
        self::assertSame($name, (new DefinitionRule($lowering))->name($lowering->form($node)->node(1))->value);
    }

    public function testCompletedKeepsLegacyNameChecksAtTheirGrammarBoundary(): void
    {
        $platform = new \SqlSemantics\Platform\MySql\Platform();
        $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql5744);
        $lowering = new \SqlSemantics\Platform\MySql\Lowering\Lowering($platform->productions($profile), new \SqlSemantics\Lowering\Leaves(), $profile);
        $name = str_repeat('p', 65);
        $node = $platform->parser($profile)->parse('ALTER TABLE t ADD PARTITION (PARTITION ' . $name . ')')->find('part_name')[0];
        $notice = (new DefinitionRule($lowering))->completed($node);
        self::assertNotNull($notice);
        self::assertTrue($notice->aborts);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Name\Problem\NameTooLong::class, $notice->problem);
        self::assertSame($name, $notice->problem->name->value);
    }

    public function testCompletedLeavesModernNamesToTheirOwningDefinition(): void
    {
        $platform = new \SqlSemantics\Platform\MySql\Platform();
        $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847);
        $lowering = new \SqlSemantics\Platform\MySql\Lowering\Lowering($platform->productions($profile), new \SqlSemantics\Lowering\Leaves(), $profile);
        $node = $platform->parser($profile)->parse('ALTER TABLE t ADD PARTITION (PARTITION ' . str_repeat('p', 65) . ')')->find('part_definition')[0];
        self::assertNull((new DefinitionRule($lowering))->completed($node));
    }

    public function testOpeningChecksTheListRoleBeforeItsChildNames(): void
    {
        $sql = 'ALTER TABLE t REORGANIZE PARTITION p0 INTO (PARTITION p1 VALUES LESS THAN MAXVALUE, PARTITION p2 VALUES LESS THAN MAXVALUE (SUBPARTITION ' . str_repeat('s', 65) . '))';
        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        $this->expectExceptionMessage('Wrong number of subpartitions defined');
        (new Semantics(Dialect::MySql))->analyze($sql);
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
        self::assertSame('ALTER TABLE t ADD PARTITION (PARTITION a (SUBPARTITION \'s\'))', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("ALTER TABLE t ADD PARTITION (PARTITION a (SUBPARTITION 's'))")->toString());
    }
}
