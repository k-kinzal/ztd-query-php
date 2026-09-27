<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Provider;

use Deriver\Model\Provider\Provider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Provider\Provider
 */
#[CoversClass(Provider::class)]
#[Small]
final class ProviderTest extends TestCase
{
    public function testIdNamesTheCapturedExport(): void
    {
        self::assertSame('example.container', (new \Tests\Fake\MiniContainer())->id());
    }
    public function testVersionIdentifiesTheCapturedExport(): void
    {
        self::assertSame('1', (new \Tests\Fake\MiniContainer())->version());
    }
}
