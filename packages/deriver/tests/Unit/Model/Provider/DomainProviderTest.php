<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Provider;

use Deriver\Model\Provider\DomainProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Provider\DomainProvider
 */
#[CoversClass(DomainProvider::class)]
#[UsesClass(\Deriver\Model\Domain\AbstractDomain::class)]
#[Small]
final class DomainProviderTest extends TestCase
{
    public function testDomainsContributesThePolicyLattice(): void
    {
        $provider = new \Tests\Fake\PolicyProvider();
        self::assertSame('example.policy', $provider->domains()[0]->id());
    }
}
