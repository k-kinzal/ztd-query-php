<?php

declare(strict_types=1);

namespace Tests\Unit\Value;

use Deriver\Value\SecretFingerprint;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Value\SecretFingerprint
 */
#[CoversClass(SecretFingerprint::class)]
#[Small]
final class SecretFingerprintTest extends TestCase
{
    public function testDigestIsStableAndDifferentFromAnUnkeyedHash(): void
    {
        $fingerprint = new SecretFingerprint();
        $first = $fingerprint->digest('low-entropy-secret');
        self::assertSame($first, $fingerprint->digest('low-entropy-secret'));
        self::assertNotSame(hash('sha256', 'low-entropy-secret'), $first);
        self::assertNotSame($first, $fingerprint->digest('another-secret'));
    }
}
