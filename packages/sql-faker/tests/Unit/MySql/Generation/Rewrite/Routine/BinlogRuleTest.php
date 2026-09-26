<?php

declare(strict_types=1);

namespace Tests\Unit\MySql\Generation\Rewrite\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Token\TerminalOccurrence;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\MySql\Generation\Rewrite\Routine\BinlogRule;

#[CoversClass(BinlogRule::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class BinlogRuleTest extends TestCase
{
    /**
     * @param list<string> $rules
     */
    #[TestWith([['sp_proc_stmt_statement', 'statement', 'binlog_base64_event'], 'DO_SYM'])]
    #[TestWith([['sp_proc_stmt_statement', 'simple_statement', 'binlog_stmt'], 'DO_SYM'])]
    #[TestWith([['statement', 'binlog_base64_event'], 'BINLOG_SYM'])]
    #[TestWith([['sp_proc_stmt_statement', 'statement', 'show'], 'BINLOG_SYM'])]
    public function testRewriteOnlyTheConflictingBodyStatement(array $rules, string $expected): void
    {
        $keyword = new TerminalOccurrence('BINLOG_SYM', 0, [], $rules);
        $literal = new TerminalOccurrence('TEXT_STRING', 1, [], $rules);
        $input = new TerminalSequence([$keyword, $literal], [$keyword, $literal]);
        $rule = new BinlogRule();
        $result = $rule->rewrite($input);
        self::assertSame([$expected, 'TEXT_STRING'], $result->names());
        self::assertSame($literal, $result->terminals[1]);
        self::assertSame($input->original, $result->original);
        self::assertSame($result, $rule->rewrite($result));
    }
}
