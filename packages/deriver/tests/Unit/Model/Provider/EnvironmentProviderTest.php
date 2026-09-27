<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Provider;

use Deriver\Model\Provider\EnvironmentProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Provider\EnvironmentProvider
 */
#[CoversClass(EnvironmentProvider::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class EnvironmentProviderTest extends TestCase
{
    public function testEnvironmentContainsOnlyExplicitExportedSettings(): void
    {
        self::assertSame('captured', (new \Tests\Fake\MiniContainer())->environment()['env:APP_NAME']->native());
    }
}
