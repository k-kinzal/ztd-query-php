<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Provider\EntryPointProvider
 */
#[CoversClass(\Deriver\Model\Provider\EntryPointProvider::class)]
#[UsesClass(\Deriver\Api\Project\EntryPoint::class)]
#[UsesClass(\Deriver\Model\Provider\DeclarationProvider::class)]
#[UsesClass(\Deriver\Model\Provider\DispatchProvider::class)]
#[UsesClass(\Deriver\Model\Provider\EnvironmentProvider::class)]
#[UsesClass(\Deriver\Model\Provider\ObservationProvider::class)]
#[UsesClass(\Deriver\Model\Provider\Provider::class)]
#[Small]
final class EntryPointProviderTest extends TestCase
{
    public function testEntriesKeepExplicitLifecycleSymbols(): void
    {
        self::assertSame('entry', (new \Tests\Fake\MiniContainer())->entries()[0]->symbol);
    }
}
