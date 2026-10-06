<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Condition\Diagnostics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionItemName;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\GetDiagnostics;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\InformationItem;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\StatementItemName;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(InformationItem::class)]
#[Medium]
final class InformationItemTest extends TestCase
{
    public function testRenderWritesAUserVariableTarget(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));
        (new InformationItem(new UserVariable(new Name('m')), ConditionItemName::MessageText))->render($out);

        self::assertSame('@m = MESSAGE_TEXT', (new Lexical())->join($out->pieces()));
    }

    public function testRenderQuotesAReservedNameTarget(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));
        (new InformationItem(new Name('select'), StatementItemName::RowCount))->render($out);

        self::assertSame('`select` = ROW_COUNT', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesTheTargetsOfAProgram(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() BEGIN DECLARE `select` TEXT; DECLARE n INT; GET DIAGNOSTICS n = NUMBER; GET DIAGNOSTICS CONDITION n `select` = MESSAGE_TEXT, @e = MYSQL_ERRNO; END');

        self::assertSame([], $create->facts->diagnostics);
        self::assertSame('CREATE PROCEDURE p() BEGIN DECLARE `select` TEXT; DECLARE n INT; GET DIAGNOSTICS n = NUMBER; GET DIAGNOSTICS CONDITION n `select` = MESSAGE_TEXT, @e = MYSQL_ERRNO; END', $create->toString());
    }

    public function testRenderWritesTheTargetsOfAStatement(): void
    {
        $get = (new Semantics(Dialect::MySql))->analyze('GET DIAGNOSTICS @n = NUMBER, @r = ROW_COUNT');
        $statement = $get->statement;
        self::assertInstanceOf(GetDiagnostics::class, $statement);
        $item = $statement->information->items[1];

        self::assertEquals(new UserVariable(new Name('r')), $item->target);
        self::assertSame(StatementItemName::RowCount, $item->item);
        self::assertSame('GET DIAGNOSTICS @n = NUMBER, @r = ROW_COUNT', $get->toString());
    }
}
