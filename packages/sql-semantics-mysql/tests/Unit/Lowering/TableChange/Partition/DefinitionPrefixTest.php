<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\TableChange\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Lowering\TableChange\Partition\DefinitionPrefix;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Partition\Problem\DefinitionIssue;

#[CoversClass(DefinitionPrefix::class)]
#[Medium]
final class DefinitionPrefixTest extends TestCase
{
    #[DataProvider('providerLegacyBounds')]
    public function testCompletedPreservesTheBoundBeforeAnUnfinishedList(GrammarRelease $release, string $clause, DefinitionIssue $issue): void
    {
        $sql = 'ALTER TABLE t PARTITION BY ' . $clause . ', PARTITION';
        $notices = (new SyntaxWarnings())->completed(new MySqlParser($release->value), $sql, strlen($sql), new LanguageProfile($release));
        self::assertCount(1, $notices);
        self::assertInstanceOf(ParseFailure::class, $notices[0][1]);
        self::assertSame($issue, $notices[0][1]->problem);
    }

    public function testCompletedLeavesModernConditionsToContextualization(): void
    {
        $sql = 'ALTER TABLE t PARTITION BY RANGE(i) (PARTITION p, PARTITION';
        self::assertSame([], (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, strlen($sql), new LanguageProfile(GrammarRelease::MySql847)));
    }

    public function testCompletedDoesNotReuseTheMethodForAnAddPartitionInstruction(): void
    {
        $sql = 'CREATE PROCEDURE p() BEGIN ALTER TABLE t PARTITION BY HASH(i) (PARTITION p); ALTER TABLE t ADD PARTITION (PARTITION p VALUES IN (1)); SELECT ?; END';
        self::assertSame([], (new SyntaxWarnings())->completed(new MySqlParser('mysql-5.7.44'), $sql, strlen($sql), new LanguageProfile(GrammarRelease::MySql5744)));
    }

    /**
     * @return iterable<string, array{GrammarRelease, string, DefinitionIssue}>
     */
    public static function providerLegacyBounds(): iterable
    {
        foreach ([GrammarRelease::MySql5651, GrammarRelease::MySql5744] as $release) {
            foreach ([
                ['RANGE(i) (PARTITION p', DefinitionIssue::MissingRangeValues],
                ['LIST(i) (PARTITION p', DefinitionIssue::MissingListValues],
                ['HASH(i) (PARTITION p VALUES IN (1)', DefinitionIssue::ListValues],
                ['LIST(i) (PARTITION p VALUES LESS THAN (1)', DefinitionIssue::RangeValues],
            ] as $index => [$clause, $issue]) {
                yield $release->value . ' ' . $index => [$release, $clause, $issue];
            }
        }
    }
}
