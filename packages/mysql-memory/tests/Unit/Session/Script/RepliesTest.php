<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Script;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Script\Replies;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Replies::class)]
#[Small]
final class RepliesTest extends TestCase
{
    public function testRunExecutesTheNextStatementOnlyAfterItsPredecessorIsConsumed(): void
    {
        $session = (new Instance())->connect();
        $answers = (new Replies($session))->run('SET @a=1; SET @a=2');
        [$first, $more] = $answers->current();

        self::assertInstanceOf(Completion::class, $first);
        self::assertTrue($more);
        self::assertSame(1, $session->variables->user('a')[0]);
        $answers->next();
        [$last, $more] = $answers->current();
        self::assertInstanceOf(Completion::class, $last);
        self::assertFalse($more);
        self::assertSame(2, $session->variables->user('a')[0]);
        $answers->next();
        self::assertFalse($answers->valid());
        self::assertSame('', $session->following);
    }

    public function testRunEndsAtTheFirstErrorAndPreservesPriorReplies(): void
    {
        $session = (new Instance())->connect();
        $answers = iterator_to_array((new Replies($session))->run('SELECT 1; SELECT missing; SET @a=2'));

        self::assertCount(2, $answers);
        self::assertInstanceOf(ResultSet::class, $answers[0][0]);
        self::assertTrue($answers[0][1]);
        self::assertInstanceOf(SqlError::class, $answers[1][0]);
        self::assertFalse($answers[1][1]);
        self::assertNull($session->variables->user('a')[0]);
    }

    public function testRunMarksProgramResultsAndItsFinalCompletion(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE PROCEDURE p() BEGIN SELECT 1; SELECT 2; END');
        $answers = iterator_to_array((new Replies($session))->run('CALL p()'));

        self::assertSame([true, true, false], array_column($answers, 1));
        self::assertInstanceOf(ResultSet::class, $answers[0][0]);
        self::assertInstanceOf(ResultSet::class, $answers[1][0]);
        self::assertInstanceOf(Completion::class, $answers[2][0]);
    }
}
