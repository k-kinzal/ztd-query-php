<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Rewrite\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\MySql\Generation\Rewrite\Replication\TablePatternRule;

#[CoversClass(TablePatternRule::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class TablePatternRuleTest extends TestCase
{
    public function testRewriteMapsEveryWrappedFilterWithoutChangingUnrelatedStrings(): void
    {
        $first = new TerminalOccurrence('TEXT_STRING', 3, [0, 1, 2], ['filter_wild_db_table_string', 'TEXT_STRING_sys_nonewline', 'TEXT_STRING_sys']);
        $plain = new TerminalOccurrence('TEXT_STRING', 4, [5], ['literal']);
        $second = new TerminalOccurrence('TEXT_STRING', 6, [7], ['filter_wild_db_table_string']);
        $other = new TerminalOccurrence('OTHER', 8, [9], ['filter_wild_db_table_string']);
        $input = new TerminalSequence([$first, $plain, $second, $other], [$first, $plain, $second, $other]);
        $rule = new TablePatternRule();
        $result = $rule->rewrite($input);
        self::assertSame(['REPLICATION_TABLE_PATTERN', 'TEXT_STRING', 'REPLICATION_TABLE_PATTERN', 'OTHER'], $result->names());
        self::assertSame([3, 4, 6, 8], array_map(static fn (TerminalOccurrence $terminal): int => $terminal->id, $result->terminals));
        self::assertSame($first->ancestors, $result->terminals[0]->ancestors);
        self::assertSame($plain, $result->terminals[1]);
        self::assertSame($other, $result->terminals[3]);
        self::assertSame($input->original, $result->original);
        self::assertSame($result, $rule->rewrite($result));
    }
}
