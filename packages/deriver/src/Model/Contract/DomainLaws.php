<?php

declare(strict_types=1);

namespace Deriver\Model\Contract;

use Deriver\Model\Domain\AbstractDomain;
use Deriver\Model\Domain\DomainFact;

/**
 * Finite contract checks for third-party domains; samples do not prove arbitrary laws.
 *
 * @visibility public
 * @example Domain checks return named counterexamples
 *     method_exists(\Deriver\Model\Contract\DomainLaws::class, 'violations') // => true
 */
final class DomainLaws
{
    /**
     * Tests lattice and widening laws over a supplied independent sample set.
     * @param AbstractDomain $domain Trusted abstract domain
     * @param list<DomainFact> $samples Independent representative facts
     * @return list<string> Detected contract violations
     */
    public function violations(AbstractDomain $domain, array $samples): array
    {
        $failures = [];
        foreach ([$domain->bottom(), $domain->top(), ...$samples] as $a) {
            if ($a->domain !== $domain->id() || !$domain->lessOrEqual($a, $a)) {
                $failures[] = 'domain-identity-or-reflexivity';
            }
            if (!$domain->lessOrEqual($domain->bottom(), $a) || !$domain->lessOrEqual($a, $domain->top())) {
                $failures[] = 'top-bottom-inclusion';
            }
            if (!$this->equivalent($domain, $domain->join($a, $a), $a)) {
                $failures[] = 'join-idempotence';
            }
            foreach ($samples as $b) {
                array_push($failures, ...$this->pair($domain, $a, $b));
                foreach ($samples as $c) {
                    if (!$this->equivalent($domain, $domain->join($domain->join($a, $b), $c), $domain->join($a, $domain->join($b, $c)))) {
                        $failures[] = 'join-associativity';
                    }
                }
            }
        }
        return array_values(array_unique($failures));
    }

    /**
     * Checks pairwise inclusion, symmetry, and widening coverage.
     * @param AbstractDomain $domain Registered lattice
     * @param DomainFact $a First sample
     * @param DomainFact $b Second sample
     * @return list<string> Violations
     */
    public function pair(AbstractDomain $domain, DomainFact $a, DomainFact $b): array
    {
        $errors = [];
        $join = $domain->join($a, $b);
        if (!$domain->lessOrEqual($a, $join) || !$domain->lessOrEqual($b, $join)) {
            $errors[] = 'join-inclusion';
        }
        if (!$this->equivalent($domain, $join, $domain->join($b, $a))) {
            $errors[] = 'join-commutativity';
        }
        $widen = $domain->widen($a, $b);
        if (!$domain->lessOrEqual($a, $widen) || !$domain->lessOrEqual($b, $widen)) {
            $errors[] = 'widen-inclusion';
        }
        return $errors;
    }

    /**
     * Compares facts by mutual inclusion, independent of their representation.
     * @param AbstractDomain $domain Registered lattice
     * @param DomainFact $a First fact
     * @param DomainFact $b Second fact
     * @return bool Whether the facts denote the same abstract set
     */
    public function equivalent(AbstractDomain $domain, DomainFact $a, DomainFact $b): bool
    {
        return $domain->lessOrEqual($a, $b) && $domain->lessOrEqual($b, $a);
    }
}
