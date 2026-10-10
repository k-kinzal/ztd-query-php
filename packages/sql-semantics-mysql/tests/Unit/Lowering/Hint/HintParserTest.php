<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Hint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Lowering\Hint\HintLexer;
use SqlSemantics\Platform\MySql\Lowering\Hint\HintParser;
use SqlSemantics\Platform\MySql\Lowering\Hint\HintRefusal;
use SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintFailure;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\KeyHint;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\StrategyHint;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\TableHint;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\VariableHint;
use SqlSemantics\Platform\MySql\Statement\Hint\HintName;

#[CoversClass(HintParser::class)]
#[Small]
final class HintParserTest extends TestCase
{
    public function testParseKeepsTheHintsBeforeAProblem(): void
    {
        $comment = (new HintParser(new HintLexer(' BKA(x) FOO ', 10, GrammarRelease::MySql847, false)))->parse();

        self::assertSame(['BKA(`x`)'], array_map(static fn ($hint): string => $hint->text(), $comment->hints));
        self::assertSame("Optimizer hint syntax error near 'FOO */ 1' at line 1", $comment->error?->message('SELECT /*+ BKA(x) FOO */ 1'));
    }

    public function testParseRefusesAnEmptyCommentAtItsEnd(): void
    {
        $comment = (new HintParser(new HintLexer(' ', 10, GrammarRelease::MySql847, false)))->parse();

        self::assertSame([], $comment->hints);
        self::assertSame("Optimizer hint syntax error near '*/' at line 1", $comment->error?->message('SELECT /*+ */ 1'));
    }

    public function testHintReadsHintNamesWithoutRegardToCase(): void
    {
        self::assertSame(HintName::NoBka, (new HintParser(new HintLexer('no_bka ( t )', 0, GrammarRelease::MySql847, false)))->hint()->name());
    }

    public function testHintRefusesAHintTheReleaseLacks(): void
    {
        $this->expectException(HintRefusal::class);

        (new HintParser(new HintLexer('SET_VAR(a=1)', 0, GrammarRelease::MySql5744, false)))->hint();
    }

    public function testHintRefusesABlockRightAfterTheParenthesisOfAHintWithoutOne(): void
    {
        $comment = (new HintParser(new HintLexer('QB_NAME( @q)', 0, GrammarRelease::MySql847, false)))->parse();

        self::assertSame(10, $comment->error?->offset);
    }

    public function testTablesReadsTablesWithTheirBlocks(): void
    {
        $hint = (new HintParser(new HintLexer('BKA(t1, t2@`q`)', 0, GrammarRelease::MySql847, false)))->hint();

        self::assertInstanceOf(TableHint::class, $hint);
        self::assertSame('BKA(`t1`, `t2`@`q`)', $hint->text());
    }

    public function testTablesRefusesATableWithABlockAfterALeadingOne(): void
    {
        $comment = (new HintParser(new HintLexer('JOIN_ORDER(@q t1, t2@q)', 0, GrammarRelease::MySql847, false)))->parse();

        self::assertSame(21, $comment->error?->offset);
    }

    public function testFixedReadsAnOptionalBlock(): void
    {
        self::assertSame('JOIN_FIXED_ORDER(@`q`)', (new HintParser(new HintLexer('JOIN_FIXED_ORDER(@q)', 0, GrammarRelease::MySql847, false)))->hint()->text());
    }

    public function testKeyReadsATableAndItsIndexes(): void
    {
        $hint = (new HintParser(new HintLexer('INDEX(@q t ka, PRIMARY)', 0, GrammarRelease::MySql847, false)))->hint();

        self::assertInstanceOf(KeyHint::class, $hint);
        self::assertSame(['ka', 'PRIMARY'], $hint->indexes);
    }

    public function testKeyRefusesAnIndexWithoutAComma(): void
    {
        self::assertSame(9, (new HintParser(new HintLexer('MRR(t ka kb)', 0, GrammarRelease::MySql847, false)))->parse()->error?->offset);
    }

    public function testStrategiesReadsACommaAfterTheBlockOfSemijoinOnly(): void
    {
        $hint = (new HintParser(new HintLexer('SEMIJOIN(@q, firstmatch,LOOSESCAN)', 0, GrammarRelease::MySql847, false)))->hint();

        self::assertInstanceOf(StrategyHint::class, $hint);
        self::assertSame(['FIRSTMATCH', 'LOOSESCAN'], $hint->strategies);
        self::assertSame(11, (new HintParser(new HintLexer('SUBQUERY(@q, INTOEXISTS)', 0, GrammarRelease::MySql847, false)))->parse()->error?->offset);
    }

    public function testTimeRefusesALimitTheServerDoesNotSupport(): void
    {
        $error = (new HintParser(new HintLexer('MAX_EXECUTION_TIME(4294967296 )', 0, GrammarRelease::MySql847, false)))->parse()->error;

        self::assertSame(HintFailure::ExecutionTime, $error?->failure);
        self::assertSame(30, $error->offset);
        self::assertSame('MAX_EXECUTION_TIME(7)', (new HintParser(new HintLexer('007 )', 0, GrammarRelease::MySql847, false)))->time()->text());
    }

    public function testVariableReadsEachKindOfValue(): void
    {
        $parser = new HintParser(new HintLexer("SET_VAR(a=16M) SET_VAR(b=.5) SET_VAR(c=1e5) SET_VAR(d='it''s')", 0, GrammarRelease::MySql847, false));
        $hints = $parser->parse()->hints;

        self::assertContainsOnlyInstancesOf(VariableHint::class, $hints);
        self::assertSame(['SET_VAR(`a` = 16777216)', 'SET_VAR(`b` = .5)', 'SET_VAR(`c` = `1e5`)', "SET_VAR(`d` = 'it''s')"], array_map(static fn ($hint): string => $hint->text(), $hints));
    }

    public function testVariableRefusesASizeAbove64Bits(): void
    {
        self::assertSame(HintFailure::Size, (new HintParser(new HintLexer('SET_VAR(a=18446744073709551616)', 0, GrammarRelease::MySql847, false)))->parse()->error?->failure);
    }

    public function testBlockAnswersNullWithoutAnAtSign(): void
    {
        self::assertNull((new HintParser(new HintLexer('t', 0, GrammarRelease::MySql847, false)))->block());
    }

    public function testTableReadsTheBlockRightAfterTheName(): void
    {
        self::assertSame('`t`@`q`', (new HintParser(new HintLexer('t@q', 0, GrammarRelease::MySql847, false)))->table(true)->text());
    }

    public function testNameRefusesABlockAfterIt(): void
    {
        $this->expectException(HintRefusal::class);

        (new HintParser(new HintLexer('q@r', 0, GrammarRelease::MySql847, false)))->name(false);
    }

    public function testAfterReadsAKeywordAsABlockName(): void
    {
        self::assertSame('bka', (new HintParser(new HintLexer('@bka', 0, GrammarRelease::MySql847, false)))->after(1));
    }

    public function testCloseAnswersTheValueBeforeTheParenthesis(): void
    {
        self::assertSame('v', (new HintParser(new HintLexer(' )', 0, GrammarRelease::MySql847, false)))->close('v'));
    }

    public function testSymbolRefusesAnotherToken(): void
    {
        $this->expectException(HintRefusal::class);

        (new HintParser(new HintLexer('x', 0, GrammarRelease::MySql847, false)))->symbol('(');
    }

    public function testSupportedAnswersTheLimitsTheServerTakes(): void
    {
        self::assertSame([true, false, false, true, false], [HintParser::supported('4294967295'), HintParser::supported('4294967296'), HintParser::supported('9223372036854775807'), HintParser::supported('18446744073709551615'), HintParser::supported('18446744073709551616')]);
    }

    public function testCompareOrdersDigitStrings(): void
    {
        self::assertSame([-1, 0, 1], [HintParser::compare('99', '100'), HintParser::compare('12', '12'), HintParser::compare('13', '12')]);
    }
}
