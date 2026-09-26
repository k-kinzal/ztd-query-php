<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Value\SecretFingerprint
 */
#[CoversClass(\Deriver\Internal\Value\SecretFingerprint::class)]
#[Small]
final class SecretFingerprintTest extends TestCase
{
    public function testDigestIsStableAndDifferentFromAnUnkeyedHash(): void
    {
        $fingerprint = new \Deriver\Internal\Value\SecretFingerprint();
        $first = $fingerprint->digest('low-entropy-secret');
        self::assertSame($first, $fingerprint->digest('low-entropy-secret'));
        self::assertNotSame(hash('sha256', 'low-entropy-secret'), $first);
        self::assertNotSame($first, $fingerprint->digest('another-secret'));
    }
}
