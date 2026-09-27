<?php

declare(strict_types=1);

namespace Tests\Unit\Query;

use Deriver\Query\CancellationToken;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Query\CancellationToken
 */
#[CoversClass(CancellationToken::class)]
#[Small]
final class CancellationTokenTest extends TestCase
{
    public function testCancelKeepsTheRequestPermanent(): void
    {
        $token = new CancellationToken();
        $token->cancel();
        $token->cancel();
        self::assertTrue($token->isRequested());
    }
    public function testIsRequestedStartsWithAnActiveToken(): void
    {
        self::assertFalse((new CancellationToken())->isRequested());
    }
}
