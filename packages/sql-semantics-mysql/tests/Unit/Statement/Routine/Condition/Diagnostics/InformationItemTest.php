<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Condition\Diagnostics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Rendering\Lexical;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionItemName;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\GetDiagnostics;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\InformationItem;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\StatementItemName;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Testing\MySql\Inspection;

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
        $create = (new Inspection())->inspect('CREATE PROCEDURE p() BEGIN DECLARE `select` TEXT; DECLARE n INT; GET DIAGNOSTICS n = NUMBER; GET DIAGNOSTICS CONDITION n `select` = MESSAGE_TEXT, @e = MYSQL_ERRNO; END');

        self::assertSame([], $create->facts->diagnostics);
        self::assertSame('CREATE PROCEDURE p() BEGIN DECLARE `select` TEXT; DECLARE n INT; GET DIAGNOSTICS n = NUMBER; GET DIAGNOSTICS CONDITION n `select` = MESSAGE_TEXT, @e = MYSQL_ERRNO; END', $create->toString());
    }

    public function testRenderWritesTheTargetsOfAStatement(): void
    {
        $get = (new Inspection())->inspect('GET DIAGNOSTICS @n = NUMBER, @r = ROW_COUNT');
        $statement = $get->statement;
        self::assertInstanceOf(GetDiagnostics::class, $statement);
        $item = $statement->information->items[1];

        self::assertEquals(new UserVariable(new Name('r')), $item->target);
        self::assertSame(StatementItemName::RowCount, $item->item);
        self::assertSame('GET DIAGNOSTICS @n = NUMBER, @r = ROW_COUNT', $get->toString());
    }
}
