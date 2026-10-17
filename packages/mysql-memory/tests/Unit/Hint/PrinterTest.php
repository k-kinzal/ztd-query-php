<?php

declare(strict_types=1);

namespace Tests\Unit\Hint;

use MySqlMemory\Hint\Printer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\BlockNameHint;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\ExecutionTimeHint;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\HintLiteral;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\HintLiteralKind;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\HintTable;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\KeyHint;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\StrategyHint;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\TableHint;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\VariableHint;
use SqlSemantics\Platform\MySql\Statement\Hint\HintName;

#[CoversClass(Printer::class)]
#[Small]
final class PrinterTest extends TestCase
{
    public function testQuoteUsesDoubleQuotesUnderAnsiQuotes(): void
    {
        self::assertSame(['`a``b`', '"a""b"'], [(new Printer())->quote('a`b'), (new Printer(true))->quote('a"b')]);
    }

    public function testTableWritesTheBlockGivenOrWritten(): void
    {
        self::assertSame(['`t`@`q`', '`t`@`r`', '`t`'], [(new Printer())->table(new HintTable('t', 'q')), (new Printer())->table(new HintTable('t'), 'r'), (new Printer())->table(new HintTable('t'))]);
    }

    public function testLevelWritesOneTableOrTheBlock(): void
    {
        $hint = new TableHint(HintName::Bka, 'q', [new HintTable('t')]);

        self::assertSame(['BKA(`t` )', 'BKA(@`q` )'], [(new Printer())->level($hint, new HintTable('t')), (new Printer())->level($hint, null)]);
    }

    public function testOrderWritesTheTablesWithoutSpaces(): void
    {
        self::assertSame('JOIN_PREFIX( `t`@`q`,`u`)', (new Printer())->order(new TableHint(HintName::JoinPrefix, null, [new HintTable('t', 'q'), new HintTable('u')])));
        self::assertSame('JOIN_FIXED_ORDER(@`select#1` )', (new Printer())->order(new TableHint(HintName::JoinFixedOrder, 'select#1', [])));
    }

    public function testIndexWritesOneIndex(): void
    {
        $hint = new KeyHint(HintName::Mrr, 'q', new HintTable('t'), ['ka']);

        self::assertSame(['MRR(`t`@`q` `ka` )', 'MRR(`t`@`q` )'], [(new Printer())->index($hint, 'ka'), (new Printer())->index($hint, null)]);
    }

    public function testKeyWritesAllTheIndexes(): void
    {
        self::assertSame('INDEX(`t`  `ka`, `kb`)', (new Printer())->key(new KeyHint(HintName::Index, null, new HintTable('t'), ['ka', 'kb'])));
        self::assertSame('NO_INDEX(`t` )', (new Printer())->key(new KeyHint(HintName::NoIndex, null, new HintTable('t'), [])));
    }

    public function testStrategyWritesTheBlockAndTheStrategies(): void
    {
        self::assertSame('NO_SEMIJOIN(  DUPSWEEDOUT)', (new Printer())->strategy(new StrategyHint(HintName::NoSemijoin, null, ['DUPSWEEDOUT'])));
        self::assertSame('SEMIJOIN(@`q` )', (new Printer())->strategy(new StrategyHint(HintName::Semijoin, 'q', [])));
    }

    public function testVariableWritesTheValueAsTheServerDoes(): void
    {
        self::assertSame([
            'SET_VAR(sort_buffer_size=7) ',
            'SET_VAR(sort_buffer_size=) ',
            "SET_VAR(sort_buffer_size='ON') ",
        ], [
            (new Printer())->variable(new VariableHint('SORT_BUFFER_SIZE', new HintLiteral(HintLiteralKind::Integer, '7')), 'sort_buffer_size'),
            (new Printer())->variable(new VariableHint('sort_buffer_size', new HintLiteral(HintLiteralKind::Decimal, '1.5')), 'sort_buffer_size'),
            (new Printer())->variable(new VariableHint('sort_buffer_size', new HintLiteral(HintLiteralKind::Word, 'ON')), 'sort_buffer_size'),
        ]);
    }

    public function testTimeWritesTheLimit(): void
    {
        self::assertSame('MAX_EXECUTION_TIME(5)', (new Printer())->time(new ExecutionTimeHint('5')));
    }

    public function testBlockWritesQbName(): void
    {
        self::assertSame('QB_NAME(`a b`)', (new Printer())->block(new BlockNameHint('a b')));
    }
}
