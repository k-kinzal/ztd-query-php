<?php

declare(strict_types=1);

namespace Tests\Unit\Result;

use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Reply::class)]
#[Small]
final class ReplyTest extends TestCase
{
    public function testWarningsOfACompletion(): void
    {
        $reply = new Completion(0, 0, 3);

        self::assertSame(3, $reply->warnings());
    }

    public function testWarningsOfAResultSet(): void
    {
        $reply = new ResultSet([], [], 2);

        self::assertSame(2, $reply->warnings());
    }

    public function testWarningsOfAStatementThatRaisesOne(): void
    {
        $session = (new Instance())->connect();
        $replies = $session->query("DO 1; SELECT 'x' + 1");

        self::assertInstanceOf(Completion::class, $replies[0]);
        self::assertInstanceOf(ResultSet::class, $replies[1]);
        self::assertSame(0, $replies[0]->warnings());
        self::assertSame(1, $replies[1]->warnings());
    }
}
