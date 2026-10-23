<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Hint\Comment;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintError;
use SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintFailure;

#[CoversClass(HintError::class)]
#[Small]
final class HintErrorTest extends TestCase
{
    public function testMessageWritesTheStatementFromTheOffsetAndItsLine(): void
    {
        self::assertSame("Unsupported MAX_EXECUTION_TIME near ') */ 1' at line 2", (new HintError(HintFailure::ExecutionTime, 41))->message("SELECT\n/*+ MAX_EXECUTION_TIME(99999999999) */ 1"));
    }

    public function testMessageWritesOnlyTheEndOfTheComment(): void
    {
        self::assertSame("Optimizer hint syntax error near '*/' at line 1", (new HintError(HintFailure::Syntax, 15, true))->message('SELECT /*+ BKA */ 1'));
    }

    public function testMessageCutsTheTextTo80Bytes(): void
    {
        self::assertSame(80, strlen(explode("'", (new HintError(HintFailure::Syntax, 0))->message(str_repeat('x', 100)))[1]));
    }

    public function testANegativeOffsetIsRefused(): void
    {
        $this->expectExceptionMessage('An offset is not negative.');

        new HintError(HintFailure::Syntax, -1);
    }
}
