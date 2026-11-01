<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Condition;

use MySqlMemory\Command\Condition\SignalDelivery;
use MySqlMemory\Result\Completion;
use MySqlMemory\Session\Diagnostics;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(SignalDelivery::class)]
#[Small]
final class SignalDeliveryTest extends TestCase
{
    public function testDeliverCompletesWithTheDeclaredWarningAndInformation(): void
    {
        $diagnostics = new Diagnostics();
        $information = ['RETURNED_SQLSTATE' => '01234', 'MESSAGE_TEXT' => 'notice'];
        $reply = (new SignalDelivery())->deliver('01234', 'notice', 1000, $information, $diagnostics);
        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame(1, $reply->warnings);
        self::assertSame([['Warning', 1000, 'notice']], $diagnostics->conditions);
        self::assertSame([$information], $diagnostics->signalled);
    }

    #[TestWith(['02000', 1643])]
    #[TestWith(['45000', 1644])]
    public function testDeliverRaisesTheDefaultErrorOfTheStateClass(string $state, int $code): void
    {
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionCode($code);
        (new SignalDelivery())->deliver($state, null, null, ['RETURNED_SQLSTATE' => $state], new Diagnostics());
    }
}
