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
        $procedureOperation = $semantics->analyze('CREATE PROCEDURE p() BEGIN END');
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $procedureOperation);
        $procedure = $procedureOperation->statement;
        self::assertInstanceOf(CreateProcedure::class, $procedure);
        $selectOperation = $semantics->analyze('SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $selectOperation);
        $select = $selectOperation->statement;

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

        (new StatementSequence())->members(['first' => new \SqlSemantics\Platform\MySql\Statement\Server\Transaction\StartTransaction()]);
    }

    public function testMemberAcceptsAProgramStatementAndAnSqlStatement(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $procedureOperation = $semantics->analyze('CREATE PROCEDURE p() BEGIN END');
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $procedureOperation);
        $procedure = $procedureOperation->statement;
        self::assertInstanceOf(CreateProcedure::class, $procedure);
        $block = $procedure->body;
        self::assertInstanceOf(Block::class, $block);
        $selectOperation = $semantics->analyze('SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $selectOperation);
        $select = $selectOperation->statement;

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
        $select = $semantics->analyze('SELECT 1');
        $set = $semantics->analyze('SET @a = 1');
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $select);
        self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $set);
        (new StatementSequence())->write($out, [$select->statement, $set->statement]);

        self::assertSame('SELECT 1; SET @a = 1;', (new Lexical())->join($out->pieces()));
        self::assertSame('CREATE PROCEDURE p() BEGIN SELECT 1; BEGIN END; END', $semantics->analyze('create procedure p() begin select 1; begin end; end')->toString());
    }
    public function testMemberRejectsAClientOnlyBinlogStatement(): void
    {
        $this->expectExceptionMessage('BINLOG is a top-level client statement, not a stored program member.');
        (new StatementSequence())->member(new \SqlSemantics\Platform\MySql\Statement\Replication\Log\BinlogEvent(new Text('A')));
    }

    public function testMemberRejectsATransactionBeginInTheBlockPosition(): void
    {
        $this->expectExceptionMessage('BEGIN in a stored program introduces a block; use START TRANSACTION for a transaction.');
        (new StatementSequence())->member(new \SqlSemantics\Platform\MySql\Statement\Server\Transaction\Begin());
    }

    public function testMemberRetainsStartTransaction(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('START TRANSACTION');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Server\Transaction\StartTransaction::class, $statement);
        self::assertSame($statement, (new StatementSequence())->member($statement));
    }

}
