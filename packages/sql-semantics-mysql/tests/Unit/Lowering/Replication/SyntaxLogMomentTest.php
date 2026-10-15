<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Replication\SyntaxLogMoment;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedLogSubquery;

#[CoversClass(SyntaxLogMoment::class)]
#[Medium]
final class SyntaxLogMomentTest extends TestCase
{
    public function testStartingRefusesBeforeAnUnfinishedPurgeExpression(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.6.51');
        $parser = $semantics->parser();
        self::assertInstanceOf(\SqlParser\Parser\ReductionParser::class, $parser);
        $sql = 'PURGE BINARY LOGS BEFORE (SELECT 1) +';
        $notices = (new SyntaxWarnings())->completed($parser, $sql, strlen($sql), $semantics->profile());

        self::assertCount(1, $notices);
        self::assertEquals(new \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure(new RefusedLogSubquery(26), true), $notices[0][1]);
    }

    public function testLegacyRetainsThePositionImmediatelyAfterTheOpeningParenthesis(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.7.44');
        $sql = 'PURGE BINARY LOGS BEFORE (  SELECT 1)';
        $failure = (new SyntaxWarnings())->rejected($semantics->parser()->parse($sql), $semantics->profile());

        self::assertInstanceOf(RejectedInput::class, $failure);
        self::assertEquals(new RefusedLogSubquery(26), $failure->problem);
        self::assertFalse($failure->aborts);
    }

    public function testRejectedKeepsTheOpeningAndCompletionPositionsSeparate(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $sql = 'PURGE BINARY LOGS BEFORE 1 + ((SELECT 1))';
        $failure = (new SyntaxWarnings())->rejected($semantics->parser()->parse($sql), $semantics->profile());

        self::assertInstanceOf(RejectedInput::class, $failure);
        self::assertEquals(new RefusedLogSubquery(29), $failure->problem);
        self::assertSame(strlen($sql), $failure->end);
        self::assertFalse($failure->aborts);
    }

    public function testRejectedLeavesQueryFreeMomentsAndOtherStatementsAlone(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertNull((new SyntaxWarnings())->rejected($semantics->parser()->parse('PURGE BINARY LOGS BEFORE DEFAULT(t.c)'), $semantics->profile()));
        self::assertNull((new SyntaxWarnings())->rejected($semantics->parser()->parse('SELECT (SELECT 1)'), $semantics->profile()));
    }

    public function testRejectedPreventsStatementLowering(): void
    {
        $this->expectException(RejectedInput::class);
        $this->expectExceptionMessage('A PURGE moment cannot contain a subquery.');

        (new Semantics(Dialect::MySql))->analyze('PURGE BINARY LOGS BEFORE EXISTS (SELECT 1)');
    }
}
