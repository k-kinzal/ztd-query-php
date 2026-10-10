<?php

declare(strict_types=1);

namespace Tests\Unit\Hint;

use MySqlMemory\Hint\Blocks;
use MySqlMemory\Hint\Printer;
use MySqlMemory\Hint\QueryBlock;
use MySqlMemory\Hint\Registration;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\BlockNameHint;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\ExecutionTimeHint;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\HintLiteral;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\HintLiteralKind;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\HintTable;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\KeyHint;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\ResourceGroupHint;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\TableHint;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\VariableHint;
use SqlSemantics\Platform\MySql\Statement\Hint\HintName;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\SystemVariables;

#[CoversClass(Registration::class)]
#[Small]
final class RegistrationTest extends TestCase
{
    public function testRegisterReadsInnerBlocksFirst(): void
    {
        $session = (new Instance())->connect();
        $blocks = (new Blocks($session->instance->dictionary, ''))->read($session->analyze('SELECT /*+ QB_NAME(qq) */ 1 WHERE 1 IN (SELECT /*+ QB_NAME(QQ) */ 1)')->statement);
        $registration = (new Registration($blocks, SystemVariables::of(GrammarRelease::MySql847), new Printer()))->register();

        self::assertSame([[3126, 'Hint QB_NAME(`qq`) is ignored as conflicting/duplicated']], $registration->warnings);
        self::assertSame(2, $registration->names['qq']);
    }

    public function testRegisterLooksUpTheBlockOfIndexAfterEveryHint(): void
    {
        $session = (new Instance())->connect();
        $blocks = (new Blocks($session->instance->dictionary, ''))->read($session->analyze('SELECT /*+ INDEX(@qq t ka) INDEX(t@qq ka) BKA(@qq) QB_NAME(qq) */ 1 FROM t')->statement);
        $registration = (new Registration($blocks, SystemVariables::of(GrammarRelease::MySql847), new Printer()))->register();

        self::assertSame([[3126, 'Hint INDEX(`t`@`qq`  `ka`) is ignored as conflicting/duplicated'], [3127, 'Query block name `qq` is not found for BKA hint']], $registration->warnings);
        self::assertSame([1], array_column($registration->accepted, 0));
    }

    public function testHintReadsGlobalHintsWhereTheyApply(): void
    {
        $registration = new Registration(new Blocks((new Instance())->dictionary, ''), SystemVariables::of(GrammarRelease::MySql847), new Printer());
        $top = new QueryBlock(1, true);
        $inner = new QueryBlock(2);
        $registration->hint($inner, new ExecutionTimeHint('5'));
        $registration->hint($top, new ExecutionTimeHint('5'));
        $registration->hint($top, new ExecutionTimeHint('6'));
        $registration->hint($inner, new ResourceGroupHint('g'));
        $registration->hint($top, new ResourceGroupHint('h'));

        self::assertSame([3125, 3126, 3515], array_column($registration->warnings, 0));
        self::assertSame(['5', 'h'], [$registration->time?->milliseconds, $registration->group?->group]);
    }

    public function testHintRefusesTimeAndGroupInAStoredProcedure(): void
    {
        $registration = new Registration(new Blocks((new Instance())->dictionary, ''), SystemVariables::of(GrammarRelease::MySql847), new Printer(), true);
        $registration->hint(new QueryBlock(1, true), new ExecutionTimeHint('5'));
        $registration->hint(new QueryBlock(1, true), new ResourceGroupHint('g'));

        self::assertSame([3125, 3515], array_column($registration->warnings, 0));
    }

    public function testNameGivesABlockOneNameNoOtherBlockHas(): void
    {
        $registration = new Registration(new Blocks((new Instance())->dictionary, ''), SystemVariables::of(GrammarRelease::MySql847), new Printer());
        $registration->names['select#1'] = 1;
        $block = new QueryBlock(2);
        $registration->name($block, new BlockNameHint('SELECT#1'));
        $registration->name($block, new BlockNameHint('q'));
        $registration->name($block, new BlockNameHint('r'));

        self::assertSame(['q', ['Hint QB_NAME(`SELECT#1`) is ignored as conflicting/duplicated', 'Hint QB_NAME(`r`) is ignored as conflicting/duplicated']], [$block->name, array_column($registration->warnings, 1)]);
    }

    public function testLevelIgnoresATableAfterTheHintOfItsBlock(): void
    {
        $registration = new Registration(new Blocks((new Instance())->dictionary, ''), SystemVariables::of(GrammarRelease::MySql847), new Printer());
        $block = new QueryBlock(1);
        $registration->level($block, new TableHint(HintName::Bka, null, [new HintTable('t')]));
        $registration->level($block, new TableHint(HintName::NoBka, null, []));
        $registration->level($block, new TableHint(HintName::Bka, null, []));
        $registration->level($block, new TableHint(HintName::Bka, null, [new HintTable('u')]));

        self::assertSame(['Hint BKA( ) is ignored as conflicting/duplicated', 'Hint BKA(`u` ) is ignored as conflicting/duplicated'], array_column($registration->warnings, 1));
    }

    public function testLevelStopsAtATableWhoseBlockIsUnknown(): void
    {
        $registration = new Registration(new Blocks((new Instance())->dictionary, ''), SystemVariables::of(GrammarRelease::MySql847), new Printer());
        $registration->level(new QueryBlock(1), new TableHint(HintName::Bka, null, [new HintTable('t1'), new HintTable('t2', 'q'), new HintTable('t3')]));

        self::assertSame(['t1'], array_map(static fn (array $entry): string => $entry[2]->name ?? '', $registration->accepted));
        self::assertSame([[3127, 'Query block name `q` is not found for BKA hint']], $registration->warnings);
    }

    public function testOrderTakesJoinPrefixOnceAndNothingWithJoinFixedOrder(): void
    {
        $registration = new Registration(new Blocks((new Instance())->dictionary, ''), SystemVariables::of(GrammarRelease::MySql847), new Printer());
        $block = new QueryBlock(1);
        $registration->order($block, new TableHint(HintName::JoinPrefix, null, [new HintTable('t')]));
        $registration->order($block, new TableHint(HintName::JoinOrder, null, [new HintTable('t')]));
        $registration->order($block, new TableHint(HintName::JoinOrder, null, [new HintTable('u')]));
        $registration->order($block, new TableHint(HintName::JoinPrefix, null, [new HintTable('u')]));
        $registration->order($block, new TableHint(HintName::JoinFixedOrder, null, []));
        $later = $registration->order($block, new TableHint(HintName::JoinSuffix, null, [new HintTable('t', 'q')]));

        self::assertSame(['Hint JOIN_PREFIX( `u`) is ignored as conflicting/duplicated', 'Hint JOIN_FIXED_ORDER( ) is ignored as conflicting/duplicated'], array_column($registration->warnings, 1));
        self::assertSame('q', $later[0][1]->block);
    }

    public function testKeyRepeatsIndexHintsWhoseIndexesOverlap(): void
    {
        $registration = new Registration(new Blocks((new Instance())->dictionary, ''), SystemVariables::of(GrammarRelease::MySql847), new Printer());
        $block = new QueryBlock(1);
        $registration->key($block, new KeyHint(HintName::Index, null, new HintTable('t'), ['ka']));
        $registration->key($block, new KeyHint(HintName::NoGroupIndex, null, new HintTable('t'), ['kb']));
        $registration->key($block, new KeyHint(HintName::NoOrderIndex, null, new HintTable('t'), []));
        $registration->key($block, new KeyHint(HintName::JoinIndex, null, new HintTable('t'), ['KA']));
        $registration->key($block, new KeyHint(HintName::IndexMerge, null, new HintTable('t'), ['ka']));

        self::assertSame([
            [3126, 'Hint NO_ORDER_INDEX(`t` ) is ignored as conflicting/duplicated'],
            [3126, 'Hint JOIN_INDEX(`t`  `KA`) is ignored as conflicting/duplicated'],
            [3614, 'Invalid number of arguments for hint INDEX_MERGE(`t`  `ka`)'],
        ], $registration->warnings);
    }

    public function testKeyReadsMrrForEachIndex(): void
    {
        $registration = new Registration(new Blocks((new Instance())->dictionary, ''), SystemVariables::of(GrammarRelease::MySql847), new Printer());
        $block = new QueryBlock(1);
        $registration->key($block, new KeyHint(HintName::Mrr, null, new HintTable('t'), ['ka']));
        $registration->key($block, new KeyHint(HintName::Mrr, null, new HintTable('t'), []));
        $registration->key($block, new KeyHint(HintName::NoMrr, null, new HintTable('t'), ['kb']));

        self::assertSame([[3126, 'Hint NO_MRR(`t` `kb` ) is ignored as conflicting/duplicated']], $registration->warnings);
        self::assertSame(['ka', null], array_column($registration->accepted, 3));
    }

    public function testSplitTakesEachIndexUnlessTheTableWasTaken(): void
    {
        $registration = new Registration(new Blocks((new Instance())->dictionary, ''), SystemVariables::of(GrammarRelease::MySql847), new Printer());
        $registration->split(new KeyHint(HintName::Mrr, null, new HintTable('t'), ['ka', 'kb']), 1, 'key|1|t|MRR');
        $registration->split(new KeyHint(HintName::NoMrr, null, new HintTable('t'), ['KA']), 1, 'key|1|t|MRR');
        $registration->split(new KeyHint(HintName::NoIcp, null, new HintTable('t'), []), 1, 'key|1|t|NO_ICP');
        $registration->split(new KeyHint(HintName::NoIcp, null, new HintTable('t'), ['ka']), 1, 'key|1|t|NO_ICP');

        self::assertSame([
            [3126, 'Hint NO_MRR(`t` `KA` ) is ignored as conflicting/duplicated'],
            [3126, 'Hint NO_ICP(`t` `ka` ) is ignored as conflicting/duplicated'],
        ], $registration->warnings);
        self::assertSame(['ka', 'kb', null], array_column($registration->accepted, 3));
    }

    public function testRepeatsTellsAHintOfTheSameKindOrOverlappingIndexes(): void
    {
        $registration = new Registration(new Blocks((new Instance())->dictionary, ''), SystemVariables::of(GrammarRelease::MySql847), new Printer());
        $registration->keys[1]['t'] = [['INDEX', ['ka']], ['INDEX_MERGE', []]];

        self::assertSame([true, true, false, true, false], [
            $registration->repeats(1, 't', 'INDEX', ['kb']),
            $registration->repeats(1, 't', 'JOIN_INDEX', ['ka', 'kc']),
            $registration->repeats(1, 't', 'GROUP_INDEX', ['kb']),
            $registration->repeats(1, 't', 'ORDER_INDEX', []),
            $registration->repeats(1, 'u', 'INDEX', []),
        ]);
    }

    public function testVariableTakesAKnownHintableVariableOnce(): void
    {
        $registration = new Registration(new Blocks((new Instance())->dictionary, ''), SystemVariables::of(GrammarRelease::MySql847), new Printer());
        $registration->variable(new VariableHint('nosuch', new HintLiteral(HintLiteralKind::Integer, '1')));
        $registration->variable(new VariableHint('autocommit', new HintLiteral(HintLiteralKind::Integer, '1')));
        $registration->variable(new VariableHint('SORT_BUFFER_SIZE', new HintLiteral(HintLiteralKind::Integer, '1')));
        $registration->variable(new VariableHint('sort_buffer_size', new HintLiteral(HintLiteralKind::Word, '2M')));

        self::assertSame([
            [3128, "Unresolved name 'nosuch' for SET_VAR hint"],
            [3637, "Variable 'autocommit' cannot be set using SET_VAR hint."],
            [3126, "Hint SET_VAR(sort_buffer_size='2M')  is ignored as conflicting/duplicated"],
        ], $registration->warnings);
        self::assertSame(['sort_buffer_size'], array_keys($registration->variables));
    }

    public function testTargetAnswersTheNamedBlockWithoutRegardToCase(): void
    {
        $registration = new Registration(new Blocks((new Instance())->dictionary, ''), SystemVariables::of(GrammarRelease::MySql847), new Printer());
        $registration->names['qq'] = 2;

        self::assertSame([1, 2, null], [$registration->target(new QueryBlock(1), null, HintName::Bka), $registration->target(new QueryBlock(1), 'QQ', HintName::Bka), $registration->target(new QueryBlock(1), 'zZ', HintName::Mrr)]);
        self::assertSame([[3127, 'Query block name `zZ` is not found for MRR hint']], $registration->warnings);
    }

    public function testTakeTellsWhetherTheObjectWasFree(): void
    {
        $registration = new Registration(new Blocks((new Instance())->dictionary, ''), SystemVariables::of(GrammarRelease::MySql847), new Printer());

        self::assertSame([true, false], [$registration->take('k', 'A()'), $registration->take('k', 'B()')]);
        self::assertSame([[3126, 'Hint B() is ignored as conflicting/duplicated']], $registration->warnings);
    }

    public function testConflictWarnsThatTheHintIsIgnored(): void
    {
        $registration = new Registration(new Blocks((new Instance())->dictionary, ''), SystemVariables::of(GrammarRelease::MySql847), new Printer());
        $registration->conflict('MAX_EXECUTION_TIME(1)');

        self::assertSame([[3126, 'Hint MAX_EXECUTION_TIME(1) is ignored as conflicting/duplicated']], $registration->warnings);
    }

    public function testWarnRecordsTheNumberAndTheMessage(): void
    {
        $registration = new Registration(new Blocks((new Instance())->dictionary, ''), SystemVariables::of(GrammarRelease::MySql847), new Printer());
        $registration->warn(\MySqlMemory\Error\Family\StatementError::HintTimeMisplaced);

        self::assertSame([[3125, 'MAX_EXECUTION_TIME hint is supported by top-level standalone SELECT statements only']], $registration->warnings);
    }

    public function testKindJoinsAHintAndItsNoForm(): void
    {
        self::assertSame(['BKA', 'MRR', 'NO_ICP', 'INDEX_MERGE'], [Registration::kind(HintName::NoBka), Registration::kind(HintName::NoMrr), Registration::kind(HintName::NoIcp), Registration::kind(HintName::NoIndexMerge)]);
    }
}
