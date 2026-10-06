<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Condition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionItemName;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Signal;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\SignalItem;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(SignalItem::class)]
#[Medium]
final class SignalItemTest extends TestCase
{
    public function testRenderWritesTheItemAndItsValue(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));
        (new SignalItem(ConditionItemName::MysqlErrno, new NumberLiteral('1001')))->render($out);

        self::assertSame('MYSQL_ERRNO = 1001', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesEveryKindOfValue(): void
    {
        $signal = (new Semantics(Dialect::MySql))->analyze("SIGNAL SQLSTATE '45000' SET message_text = @m, mysql_errno = NULL, class_origin = x'41', table_name = 't'");
        $statement = $signal->statement;
        self::assertInstanceOf(Signal::class, $statement);

        self::assertSame(
            [ConditionItemName::MessageText, ConditionItemName::MysqlErrno, ConditionItemName::ClassOrigin, ConditionItemName::TableName],
            array_map(static fn (SignalItem $item): ConditionItemName => $item->name, $statement->items),
        );
        self::assertEquals(new UserVariable(new Name('m')), $statement->items[0]->value);
        self::assertSame("SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = @m, MYSQL_ERRNO = NULL, CLASS_ORIGIN = x'41', TABLE_NAME = 't'", $signal->toString());
    }

    public function testRefusesTheReturnedSqlstate(): void
    {
        $this->expectExceptionMessage('RETURNED_SQLSTATE is set by the condition value, not by an item.');

        new SignalItem(ConditionItemName::ReturnedSqlstate, new UserVariable(new Name('s')));
    }
}
