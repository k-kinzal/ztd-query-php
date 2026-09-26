<?php

declare(strict_types=1);

namespace Tests\Unit\Api\Execution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Api\Execution\CancellationToken
 */
#[CoversClass(\Deriver\Api\Execution\CancellationToken::class)]
#[Small]
final class CancellationTokenTest extends TestCase
{
    public function testCancelKeepsTheRequestPermanent(): void
    {
        $token = new \Deriver\Api\Execution\CancellationToken();
        $token->cancel();
        $token->cancel();
        self::assertTrue($token->isRequested());
    }
    public function testIsRequestedStartsWithAnActiveToken(): void
    {
        self::assertFalse((new \Deriver\Api\Execution\CancellationToken())->isRequested());
    }
}
