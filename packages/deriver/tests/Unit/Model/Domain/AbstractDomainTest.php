<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Domain;

use Deriver\Model\Domain\AbstractDomain;
use Deriver\Model\Domain\DomainFact;
use Deriver\Value\Projection;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Domain\AbstractDomain
 */
#[CoversClass(AbstractDomain::class)]
#[UsesClass(DomainFact::class)]
#[UsesClass(Projection::class)]
#[UsesClass(Term::class)]
#[Small]
final class AbstractDomainTest extends TestCase
{
    public function testIdNamesTheDomain(): void
    {
        self::assertSame('example.policy', (new \Tests\Fake\PolicyDomain())->id());
    }
    public function testVersionNamesTheSemanticRevision(): void
    {
        self::assertSame('1', (new \Tests\Fake\PolicyDomain())->version());
    }
    public function testBottomRepresentsNoPossibilities(): void
    {
        self::assertSame('bottom', (new \Tests\Fake\PolicyDomain())->bottom()->representation->kind);
    }
    public function testTopRepresentsEveryPolicy(): void
    {
        self::assertSame('top', (new \Tests\Fake\PolicyDomain())->top()->representation->kind);
    }
    public function testLessOrEqualIncludesBottomAndRejectsReverseInclusion(): void
    {
        $domain = new \Tests\Fake\PolicyDomain();
        self::assertTrue($domain->lessOrEqual($domain->bottom(), $domain->top()));
        self::assertFalse($domain->lessOrEqual($domain->top(), $domain->bottom()));
    }
    public function testJoinPreservesBothDisjointRecords(): void
    {
        $domain = new \Tests\Fake\PolicyDomain();
        $a = new DomainFact($domain->id(), Term::fromNative(['ttl' => 1]));
        $b = new DomainFact($domain->id(), Term::fromNative(['ttl' => 2]));
        $join = $domain->join($a, $b);
        self::assertTrue($domain->lessOrEqual($a, $join));
        self::assertTrue($domain->lessOrEqual($b, $join));
    }
    public function testWidenIncludesThePreviousAndNextApproximation(): void
    {
        $domain = new \Tests\Fake\PolicyDomain();
        self::assertSame('top', $domain->widen($domain->bottom(), $domain->top())->representation->kind);
    }
    public function testProjectSelectsOneKnownPolicyField(): void
    {
        $domain = new \Tests\Fake\PolicyDomain();
        $fact = new DomainFact($domain->id(), Term::fromNative(['ttl' => 3]));
        self::assertSame(3, $domain->project($fact, new Projection(['ttl']))->representation->native());
    }
}
