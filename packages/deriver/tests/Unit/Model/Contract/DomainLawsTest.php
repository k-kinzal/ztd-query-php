<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Contract;

use Deriver\Model\Contract\DomainLaws;
use Deriver\Model\Domain\DomainFact;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Contract\DomainLaws
 */
#[CoversClass(DomainLaws::class)]
#[UsesClass(DomainFact::class)]
#[UsesClass(Term::class)]
#[Small]
final class DomainLawsTest extends TestCase
{
    public function testViolationsAcceptsTheIndependentFlatLattice(): void
    {
        $domain = new \Tests\Fake\PolicyDomain();
        $sample = new DomainFact($domain->id(), Term::fromNative(['ttl' => 3]));
        self::assertSame([], (new DomainLaws())->violations($domain, [$sample]));
    }
    public function testPairChecksJoinAndWidenCoverage(): void
    {
        $domain = new \Tests\Fake\PolicyDomain();
        self::assertSame([], (new DomainLaws())->pair($domain, $domain->bottom(), $domain->top()));
    }
    public function testEquivalentUsesMutualInclusion(): void
    {
        $domain = new \Tests\Fake\PolicyDomain();
        self::assertTrue((new DomainLaws())->equivalent($domain, $domain->top(), $domain->top()));
        self::assertFalse((new DomainLaws())->equivalent($domain, $domain->top(), $domain->bottom()));
    }
}
