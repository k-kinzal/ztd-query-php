<?php

declare(strict_types=1);

namespace Tests\ModelContract;

use Deriver\Model\Contract\DomainLaws;
use Deriver\Model\Domain\DomainFact;
use Deriver\Query\ReturnQuery;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Analysis;
use Tests\Fake\PolicyDomain;
use Tests\Fake\PolicyModels;

/**
 * AC-14: choice, composition, and overwrite remain distinct application operations.
 */
#[CoversNothing]
#[Small]
final class PolicyContractTest extends TestCase
{
    /**
     * Verifies composition preserves alternative correlation.
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testCompositionPreservesAlternativeCorrelation(): void
    {
        $session = Analysis::session('<?php function target(bool $choice){$a=$choice?["ttl"=>10,"tags"=>["a"]]:["ttl"=>20,"tags"=>["b"]];return policy_compose($a,["ttl"=>15,"tags"=>["c"]]);}', PolicyModels::configuration());
        $result = $session->derive(new ReturnQuery('target'));
        self::assertCount(2, $result->normalOutcomes);
        self::assertSame(['ttl' => 10, 'tags' => ['a', 'c']], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame(['ttl' => 15, 'tags' => ['b', 'c']], $result->normalOutcomes[1]->values['return']->native());
        self::assertSame([], $result->frontiers);
    }

    /**
     * Verifies overwrite keeps unrelated fields.
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testOverwriteKeepsUnrelatedFields(): void
    {
        $session = Analysis::session('<?php function target(){return policy_override(["ttl"=>10,"tags"=>["a"]],["ttl"=>30]);}', PolicyModels::configuration());
        $result = $session->derive(new ReturnQuery('target'));
        self::assertSame(['ttl' => 30, 'tags' => ['a']], $result->normalOutcomes[0]->values['return']->native());
    }

    /**
     * Verifies unknown tags preserve independent time to live.
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testUnknownTagsPreserveIndependentTimeToLive(): void
    {
        $session = Analysis::session('<?php function target(){return policy_uncertain_tags(["ttl"=>10,"tags"=>["a"]],["ttl"=>20]);}', PolicyModels::configuration());
        $result = $session->derive(new ReturnQuery('target'));
        self::assertSame(10, $result->normalOutcomes[0]->values['return']->operands['ttl']->native());
        self::assertSame('opaque', $result->normalOutcomes[0]->values['return']->operands['tags']->kind);
    }

    /**
     * Verifies independent domain satisfies finite lattice contracts.
     */
    public function testIndependentDomainSatisfiesFiniteLatticeContracts(): void
    {
        $domain = new PolicyDomain();
        $a = new DomainFact('example.policy', Term::fromNative(['ttl' => 10]));
        $b = new DomainFact('example.policy', Term::fromNative(['ttl' => 20]));
        self::assertSame([], (new DomainLaws())->violations($domain, [$domain->bottom(), $a, $b, $domain->top()]));
        self::assertSame('top', $domain->join($a, $b)->representation->kind);
    }
}
