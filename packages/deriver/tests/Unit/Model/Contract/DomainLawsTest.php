<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Contract\DomainLaws
 */
#[CoversClass(\Deriver\Model\Contract\DomainLaws::class)]
#[UsesClass(\Deriver\Model\Domain\DomainFact::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class DomainLawsTest extends TestCase
{
    public function testViolationsAcceptsTheIndependentFlatLattice(): void
    {
        $domain = new \Tests\Fake\PolicyDomain();
        $sample = new \Deriver\Model\Domain\DomainFact($domain->id(), \Deriver\Value\Term::fromNative(['ttl' => 3]));
        self::assertSame([], (new \Deriver\Model\Contract\DomainLaws())->violations($domain, [$sample]));
    }
    public function testPairChecksJoinAndWidenCoverage(): void
    {
        $domain = new \Tests\Fake\PolicyDomain();
        self::assertSame([], (new \Deriver\Model\Contract\DomainLaws())->pair($domain, $domain->bottom(), $domain->top()));
    }
    public function testEquivalentUsesMutualInclusion(): void
    {
        $domain = new \Tests\Fake\PolicyDomain();
        self::assertTrue((new \Deriver\Model\Contract\DomainLaws())->equivalent($domain, $domain->top(), $domain->top()));
        self::assertFalse((new \Deriver\Model\Contract\DomainLaws())->equivalent($domain, $domain->top(), $domain->bottom()));
    }
}
