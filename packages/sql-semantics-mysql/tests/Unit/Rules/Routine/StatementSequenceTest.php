<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Rules\Routine\StatementSequence;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(StatementSequence::class)]
#[Medium]
final class StatementSequenceTest extends TestCase
{
    public function testMembersKeepsProgramStatementsAndSqlStatementsInOrder(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $procedure = $semantics->analyze('CREATE PROCEDURE p() BEGIN END')->statement;
        self::assertInstanceOf(CreateProcedure::class, $procedure);
        $select = $semantics->analyze('SELECT 1')->statement;

        self::assertSame([$procedure->body, $select], (new StatementSequence())->members([$procedure->body, $select], 1));
        self::assertSame([], (new StatementSequence())->members([]));
    }

    public function testMembersRefusesAListShorterThanTheMinimum(): void
    {
        $this->expectExceptionMessage('A statement list holds statements.');

        (new StatementSequence())->members([], 1);
    }

    public function testMembersRefusesAMemberThatIsNoNode(): void
    {
        $this->expectExceptionMessage('A statement list holds statements.');

        (new StatementSequence())->members(['SELECT 1']);
    }

    public function testMembersRefusesAListWithKeys(): void
    {
        $this->expectExceptionMessage('A statement list holds statements.');

        (new StatementSequence())->members(['first' => (new Semantics(Dialect::MySql))->analyze('SELECT 1')->statement]);
    }

    public function testMemberAcceptsAProgramStatementAndAnSqlStatement(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $procedure = $semantics->analyze('CREATE PROCEDURE p() BEGIN END')->statement;
        self::assertInstanceOf(CreateProcedure::class, $procedure);
        $block = $procedure->body;
        self::assertInstanceOf(Block::class, $block);
        $select = $semantics->analyze('SELECT 1')->statement;

        self::assertSame($block, (new StatementSequence())->member($block));
        self::assertSame($select, (new StatementSequence())->member($select));
    }

    public function testMemberRefusesANodeThatIsNoStatement(): void
    {
        $this->expectExceptionMessage('A member of a stored program is a program statement or an SQL statement.');

        (new StatementSequence())->member(new Text('x'));
    }

    public function testWriteEndsEveryStatementWithASemicolon(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $out = new Output(new Codec(GrammarRelease::MySql910));
        (new StatementSequence())->write($out, [$semantics->analyze('SELECT 1')->statement, $semantics->analyze('SET @a = 1')->statement]);

        self::assertSame('SELECT 1; SET @a = 1;', (new Lexical())->join($out->pieces()));
        self::assertSame('CREATE PROCEDURE p() BEGIN SELECT 1; BEGIN END; END', $semantics->analyze('create procedure p() begin select 1; begin end; end')->toString());
    }
}
