<?php

declare(strict_types=1);

namespace Tests\Fake;

use Deriver\Model\Domain\AbstractDomain;
use Deriver\Model\Provider\DomainProvider;
use Deriver\Model\Provider\RefinementModel;
use Deriver\Value\Term;
use Override;

/**
 * Registers the example policy lattice and a guaranteed branch implication.
 * @visibility root
 */
final class PolicyProvider implements DomainProvider, RefinementModel
{
    /**
     * @return string Stable provider identity
     */
    #[Override]
    public function id(): string
    {
        return 'example.policy-provider';
    }

    /**
     * @return string Semantic version
     */
    #[Override]
    public function version(): string
    {
        return '1';
    }

    /**
     * @return list<AbstractDomain> Captured domains
     */
    #[Override]
    public function domains(): array
    {
        return [new PolicyDomain()];
    }

    /**
     * @param Term $predicate Branch predicate
     * @param bool $truth Selected polarity
     * @return Term Guaranteed implication
     */
    #[Override]
    public function refine(Term $predicate, bool $truth): Term
    {
        return $truth ? $predicate : new Term('unary', '!', [$predicate]);
    }
}
