<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Provider\DomainProvider
 */
#[CoversClass(\Deriver\Model\Provider\DomainProvider::class)]
#[Small]
final class DomainProviderTest extends TestCase
{
    public function testDomainsContributesThePolicyLattice(): void
    {
        $provider = new \Tests\Fake\PolicyProvider();
        self::assertSame('example.policy', $provider->domains()[0]->id());
    }
}
