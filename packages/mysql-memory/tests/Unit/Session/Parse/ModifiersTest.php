<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Modifiers;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;

#[CoversClass(Modifiers::class)]
#[Small]
final class ModifiersTest extends TestCase
{
    public function testScanAnswersTheWarningsOfTheTokensBeforeAnOffset(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $tokens = $session->semantics()->parser()->tokenize('INSERT DELAYED INTO t SELECT SQL_NO_CACHE 1 FROM t PROCEDURE ANALYSE()');
        [$warned, $conflict] = (new Modifiers($tokens, GrammarRelease::MySql5744))->scan(1000);

        self::assertSame([7 => 'INSERT DELAYED is no longer supported. The statement was converted to INSERT.', 29 => "'SQL_NO_CACHE' is deprecated and will be removed in a future release.", 61 => "'PROCEDURE ANALYSE' is deprecated and will be removed in a future release."], array_map(static fn ($construct): string => $construct->value, $warned));
        self::assertNull($conflict);
    }

    public function testDeprecatedAnswersTheDeprecatedConstructOfATokenInTheWordsOfTheRelease(): void
    {
        $modifiers = new Modifiers([], GrammarRelease::MySql5744);

        self::assertSame(
            [Deprecated::DelayedInsert, Deprecated::InsertDelayed, Deprecated::ReplaceDelayed, Deprecated::ProcedureAnalyse, null],
            [
                $modifiers->deprecated('DELAYED_SYM', 'INSERT', GrammarRelease::MySql5651),
                $modifiers->deprecated('DELAYED_SYM', 'INSERT', GrammarRelease::MySql5744),
                $modifiers->deprecated('DELAYED_SYM', 'REPLACE', GrammarRelease::MySql5744),
                $modifiers->deprecated('ANALYSE_SYM', 'PROCEDURE_SYM', GrammarRelease::MySql5651),
                $modifiers->deprecated('DELAYED_SYM', 'SELECT_SYM', GrammarRelease::MySql5744),
            ],
        );
    }

    public function testScanAnswersAllWithDistinctWhereTheModifiersEnd(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $tokens = $session->semantics()->parser()->tokenize('SELECT ALL DISTINCT 1 FROM (t UNION SELECT 1)');

        self::assertSame(['ALL', 'DISTINCT'], (new Modifiers($tokens, GrammarRelease::MySql5651))->scan(31, 31)[1]);
    }

    public function testSubqueryAnswersWhetherASelectStartsASubquery(): void
    {
        $tokens = (new Instance('5.7.44'))->connect()->semantics()->parser()->tokenize('SELECT 1 FROM t WHERE x IN (SELECT 1) UNION (SELECT 2)');

        self::assertSame([false, true, false], [(new Modifiers($tokens, GrammarRelease::MySql5744))->subquery($tokens, 0, [], 0), (new Modifiers($tokens, GrammarRelease::MySql5744))->subquery($tokens, 8, [0 => false], 1), (new Modifiers($tokens, GrammarRelease::MySql5744))->subquery($tokens, 14, [0 => false], 1)]);
    }

    public function testSubqueryLeavesTheQueryOfAnInsertAfterItsColumns(): void
    {
        $tokens = (new Instance('5.7.44'))->connect()->semantics()->parser()->tokenize('INSERT INTO t (a) (SELECT 1)');

        self::assertFalse((new Modifiers($tokens, GrammarRelease::MySql5744))->subquery($tokens, 8, [], 1));
    }

    public function testEndedPrefersAQueryCacheModifierWrittenLastInMySql57(): void
    {
        $modifiers = new Modifiers([], GrammarRelease::MySql5744);

        self::assertSame([['SQL_CACHE', ''], ['ALL', 'DISTINCT'], null], [$modifiers->ended(['ALL' => true, 'DISTINCT' => true, 'SQL_CACHE_SYM' => true], true, true), $modifiers->ended(['SQL_CACHE_SYM' => true, 'ALL' => true, 'DISTINCT' => true], true, true), $modifiers->ended(['SQL_CACHE_SYM' => true], true, false)]);
    }

    public function testStoppedEndsAtAConflictWhereTheModifiersEndOnlyInMySql56(): void
    {
        $legacy = new Modifiers([], GrammarRelease::MySql5651);
        $legacy->exclusive = ['ALL', 'DISTINCT'];
        $modern = new Modifiers([], GrammarRelease::MySql5744);
        $modern->exclusive = ['ALL', 'DISTINCT'];

        self::assertSame([true, false], [$legacy->stopped(), $modern->stopped()]);
    }

    public function testReadFollowsTheParenthesesClausesAndBlocks(): void
    {
        $tokens = (new Instance('5.7.44'))->connect()->semantics()->parser()->tokenize('SELECT 1 FROM (t)');
        $modifiers = new Modifiers($tokens, GrammarRelease::MySql5744);

        $modifiers->read(0, $tokens[0], false);
        $modifiers->read(1, $tokens[1], false);
        $modifiers->read(2, $tokens[2], false);
        $modifiers->read(3, $tokens[3], false);

        self::assertSame([1, [0 => true], 1, false], [$modifiers->depth, $modifiers->clauses, $modifiers->blocks, $modifiers->listing]);
    }

    public function testSelectStartsTheModifiersOfASubquery(): void
    {
        $tokens = (new Instance('5.7.44'))->connect()->semantics()->parser()->tokenize('SELECT 1 FROM t WHERE x IN (SELECT 1)');
        $modifiers = new Modifiers($tokens, GrammarRelease::MySql5744);
        $modifiers->written = ['ALL' => true];

        $modifiers->select(8, false);

        self::assertSame([true, true, 0, []], [$modifiers->listing, $modifiers->nested, $modifiers->blocks, $modifiers->written]);
    }

    public function testOptionRefusesAQueryCacheModifierAfterAnotherInMySql57(): void
    {
        $tokens = (new Instance('5.7.44'))->connect()->semantics()->parser()->tokenize('SELECT SQL_CACHE SQL_NO_CACHE 1');
        $modifiers = new Modifiers($tokens, GrammarRelease::MySql5744);

        $modifiers->option($tokens[1], true, null);
        $modifiers->option($tokens[2], true, null);

        self::assertSame([['SQL_CACHE', 'SQL_NO_CACHE'], [7, 17]], [$modifiers->conflict, array_keys($modifiers->warned)]);
    }
}
