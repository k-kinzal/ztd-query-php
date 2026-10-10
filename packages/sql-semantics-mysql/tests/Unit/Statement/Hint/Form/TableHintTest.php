<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Hint\Form;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\HintTable;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\TableHint;
use SqlSemantics\Platform\MySql\Statement\Hint\HintName;

#[CoversClass(TableHint::class)]
#[Small]
final class TableHintTest extends TestCase
{
    public function testNameAnswersTheHint(): void
    {
        self::assertSame(HintName::JoinPrefix, (new TableHint(HintName::JoinPrefix, null, [new HintTable('t')]))->name());
    }

    public function testTextWritesTheBlockAndTheTables(): void
    {
        self::assertSame('BKA()', (new TableHint(HintName::Bka, null, []))->text());
        self::assertSame('NO_BNL(@`qb`)', (new TableHint(HintName::NoBnl, 'qb', []))->text());
        self::assertSame('JOIN_ORDER(@`qb` `t`, `u`)', (new TableHint(HintName::JoinOrder, 'qb', [new HintTable('t'), new HintTable('u')]))->text());
        self::assertSame('JOIN_FIXED_ORDER()', (new TableHint(HintName::JoinFixedOrder, null, []))->text());
    }

    public function testATableNamesNoBlockAfterALeadingOne(): void
    {
        $this->expectExceptionMessage('A table names its query block only when the hint names none.');

        new TableHint(HintName::Bka, 'qb', [new HintTable('t', 'other')]);
    }

    public function testJoinFixedOrderNamesNoTable(): void
    {
        $this->expectExceptionMessage('JOIN_FIXED_ORDER names no table.');

        new TableHint(HintName::JoinFixedOrder, null, [new HintTable('t')]);
    }

    public function testAnotherFormIsRefused(): void
    {
        $this->expectExceptionMessage('A table hint is a table-level or join order hint.');

        new TableHint(HintName::Index, null, []);
    }
}
