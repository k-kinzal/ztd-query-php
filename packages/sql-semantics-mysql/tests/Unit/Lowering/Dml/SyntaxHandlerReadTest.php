<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Dml\SyntaxHandlerRead;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\RefusedSubquery;

#[CoversClass(SyntaxHandlerRead::class)]
#[Medium]
final class SyntaxHandlerReadTest extends TestCase
{
    public function testStartingRefusesBeforeAnUnfinishedHandlerReadExpression(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.6.51');
        $parser = $semantics->parser();
        self::assertInstanceOf(\SqlParser\Parser\ReductionParser::class, $parser);
        $sql = 'HANDLER h READ FIRST WHERE (SELECT 1) +';
        $notices = (new SyntaxWarnings())->completed($parser, $sql, strlen($sql), $semantics->profile());

        self::assertCount(1, $notices);
        self::assertEquals(new \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure(new RefusedSubquery(28), true), $notices[0][1]);
    }

    public function testRejectedRetainsThePositionImmediatelyAfterTheOpeningParenthesis(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.7.44');
        $sql = 'HANDLER h READ FIRST WHERE (  SELECT 1)';
        $failure = (new SyntaxWarnings())->rejected($semantics->parser()->parse($sql), $semantics->profile());

        self::assertInstanceOf(RejectedInput::class, $failure);
        self::assertEquals(new RefusedSubquery(28), $failure->problem);
        self::assertFalse($failure->aborts);
    }

    public function testRejectedKeepsTheOpeningAndCompletionPositionsSeparate(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $sql = 'HANDLER h READ FIRST WHERE 1 + ((SELECT 1))';
        $failure = (new SyntaxWarnings())->rejected($semantics->parser()->parse($sql), $semantics->profile());

        self::assertInstanceOf(RejectedInput::class, $failure);
        self::assertEquals(new RefusedSubquery(31), $failure->problem);
        self::assertSame(strlen($sql), $failure->end);
        self::assertFalse($failure->aborts);
    }

    public function testRejectedLeavesQueryFreeReadsAndOtherStatementsAlone(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertNull((new SyntaxWarnings())->rejected($semantics->parser()->parse('HANDLER h READ FIRST WHERE DEFAULT(t.c)'), $semantics->profile()));
        self::assertNull((new SyntaxWarnings())->rejected($semantics->parser()->parse('SELECT (SELECT 1)'), $semantics->profile()));
    }

    public function testRejectedPreventsStatementLowering(): void
    {
        $this->expectException(RejectedInput::class);
        $this->expectExceptionMessage('A HANDLER read cannot contain a subquery.');

        (new Semantics(Dialect::MySql))->analyze('HANDLER h READ FIRST WHERE EXISTS (SELECT 1)');
    }
}
