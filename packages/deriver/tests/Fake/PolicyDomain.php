<?php

declare(strict_types=1);

namespace Tests\Fake;

use Deriver\Model\Domain\AbstractDomain;
use Deriver\Model\Domain\DomainFact;
use Deriver\Value\Projection;
use Deriver\Value\Term;
use Override;

/**
 * Independent flat domain whose alternatives are complete policy records.
 * @visibility root
 */
final class PolicyDomain implements AbstractDomain
{
    /**
     * @return string Domain identity
     */
    #[Override]
    public function id(): string
    {
        return 'example.policy';
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
     * @return DomainFact Empty set
     */
    #[Override]
    public function bottom(): DomainFact
    {
        return new DomainFact($this->id(), new Term('bottom'));
    }
    /**
     * @return DomainFact All policy records
     */
    #[Override]
    public function top(): DomainFact
    {
        return new DomainFact($this->id(), new Term('top'));
    }
    /**
     * @param DomainFact $a Contained fact
     * @param DomainFact $b Containing fact
     * @return bool Flat-lattice inclusion
     */
    #[Override]
    public function lessOrEqual(DomainFact $a, DomainFact $b): bool
    {
        return $a->representation->kind === 'bottom' || $b->representation->kind === 'top' || ($a->representation->isConcrete() && $b->representation->isConcrete() && $a->representation->native() === $b->representation->native());
    }
    /**
     * @param DomainFact $a First possibility
     * @param DomainFact $b Second possibility
     * @return DomainFact Inclusive alternative
     */
    #[Override]
    public function join(DomainFact $a, DomainFact $b): DomainFact
    {
        if ($this->lessOrEqual($a, $b)) {
            return $b;
        }
        return $this->lessOrEqual($b, $a) ? $a : $this->top();
    }
    /**
     * @param DomainFact $previous Previous approximation
     * @param DomainFact $next New approximation
     * @return DomainFact Finite widening
     */
    #[Override]
    public function widen(DomainFact $previous, DomainFact $next): DomainFact
    {
        return $this->join($previous, $next);
    }
    /**
     * @param DomainFact $value Policy record
     * @param Projection $projection Selected fields
     * @return DomainFact Projected fact
     */
    #[Override]
    public function project(DomainFact $value, Projection $projection): DomainFact
    {
        $term = $value->representation;
        foreach ($projection->path as $key) {
            $term = $term->operands[$key] ?? new Term('top');
        }
        return new DomainFact($this->id(), $term);
    }
}
