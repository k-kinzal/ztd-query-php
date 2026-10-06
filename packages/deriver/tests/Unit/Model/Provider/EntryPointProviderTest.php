<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Provider;

use Deriver\Model\Provider\EntryPointProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Provider\EntryPointProvider
 */
#[CoversClass(EntryPointProvider::class)]
#[UsesClass(\Deriver\Project\EntryPoint::class)]
#[Small]
final class EntryPointProviderTest extends TestCase
{
    public function testEntriesKeepExplicitLifecycleSymbols(): void
    {
        self::assertSame('entry', (new \Tests\Fake\MiniContainer())->entries()[0]->symbol);
    }
}
