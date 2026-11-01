<?php

declare(strict_types=1);

namespace Tests\Unit\Concurrency;

use MySqlMemory\Concurrency\Isolation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\IsolationLevel;

#[CoversClass(Isolation::class)]
#[Small]
final class IsolationTest extends TestCase
{
    public function testNamedReadsANameInAnyCaseOrANumber(): void
    {
        self::assertSame(
            [Isolation::ReadCommitted, Isolation::Serializable, Isolation::ReadUncommitted, null, null, null],
            [Isolation::named('read-committed'), Isolation::named('3'), Isolation::named('0'), Isolation::named('REPEATABLE READ'), Isolation::named('4'), Isolation::named('serializable ')],
        );
    }

    public function testOfAnswersTheLevelSetTransactionNames(): void
    {
        self::assertSame([Isolation::RepeatableRead, Isolation::ReadUncommitted], [Isolation::of(IsolationLevel::RepeatableRead), Isolation::of(IsolationLevel::ReadUncommitted)]);
    }
}
