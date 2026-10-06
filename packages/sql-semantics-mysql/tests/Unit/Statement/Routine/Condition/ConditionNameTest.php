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
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionName;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Signal;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(ConditionName::class)]
#[Medium]
final class ConditionNameTest extends TestCase
{
    public function testRenderWritesThePlainName(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));
        (new ConditionName(new Name('gone')))->render($out);

        self::assertSame('gone', (new Lexical())->join($out->pieces()));
    }

    public function testRenderQuotesAReservedWord(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql5651));
        (new ConditionName(new Name('select')))->render($out);

        self::assertSame('`select`', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesTheNameASignalRaises(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze("CREATE PROCEDURE p() BEGIN DECLARE `select` CONDITION FOR SQLSTATE '45000'; SIGNAL `select`; END");
        $statement = $create->statement;
        self::assertInstanceOf(CreateProcedure::class, $statement);
        $body = $statement->body;
        self::assertInstanceOf(Block::class, $body);
        $signal = $body->statements[0];
        self::assertInstanceOf(Signal::class, $signal);
        self::assertInstanceOf(ConditionName::class, $signal->condition);

        self::assertSame('select', $signal->condition->name->value);
        self::assertSame([], $create->facts->diagnostics);
        self::assertSame("CREATE PROCEDURE p() BEGIN DECLARE `select` CONDITION FOR SQLSTATE '45000'; SIGNAL `select`; END", $create->toString());
    }
}
