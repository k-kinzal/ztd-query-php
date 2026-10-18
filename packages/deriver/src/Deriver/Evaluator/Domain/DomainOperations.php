<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Domain;

use Deriver\Deriver\Evaluator\Projection;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Exception\EvaluationContractException;

/**
 * Checks domain laws and inclusion before admitting facts into the evaluation.
 * @visibility root
 */
final class DomainOperations
{
    /**
     * Validates a domain before registering its identity and version.
     * @param AbstractDomain $domain Registered implementation
     * @return array{string, string} Identity and semantic version
     * @throws EvaluationContractException If the domain violates a lattice law
     */
    public function registration(AbstractDomain $domain): array
    {
        $violations = (new DomainLaws())->violations($domain, [$domain->bottom(), $domain->top()]);
        if ($violations !== []) {
            throw new EvaluationContractException('Invalid domain registration: ' . implode(', ', $violations));
        }
        return [$domain->id(), $domain->version()];
    }

    /**
     * Applies join, widening, or projection and checks the returned fact.
     * @param AbstractDomain $domain Registered lattice
     * @param string $operation join, widen, or project
     * @param Term $left First encoded domain fact
     * @param Term|null $right Second fact for join or widening
     * @param Projection $projection Requested fields
     * @return Term Checked encoded fact
     * @throws EvaluationContractException If the operation or returned fact violates its contract
     */
    public function apply(AbstractDomain $domain, string $operation, Term $left, ?Term $right = null, Projection $projection = new Projection()): Term
    {
        $a = new DomainFact($domain->id(), $left->operands['representation'] ?? Term::opaque('INVALID_DOMAIN_FACT'));
        $b = new DomainFact($domain->id(), $right?->operands['representation'] ?? $a->representation);
        $result = match ($operation) {
            'join' => $domain->join($a, $b),
            'widen' => $domain->widen($a, $b),
            'project' => $domain->project($a, $projection),
            default => throw new EvaluationContractException('Unknown domain operation: ' . $operation),
        };
        if ($result->domain !== $domain->id() || ($operation !== 'project' && (!$domain->lessOrEqual($a, $result) || !$domain->lessOrEqual($b, $result)))) {
            throw new EvaluationContractException($domain->id() . ': ' . $operation . ' returned a fact outside its declared contract.');
        }
        return $result->term();
    }

    /**
     * Tests inclusion without treating a provider failure as a negative answer.
     * @param AbstractDomain $domain Registered lattice
     * @param Term $upper Candidate containing fact
     * @param Term $lower Candidate contained fact
     * @return bool Whether the domain establishes inclusion
     */
    public function contains(AbstractDomain $domain, Term $upper, Term $lower): bool
    {
        return $domain->lessOrEqual(new DomainFact($domain->id(), $lower->operands['representation'] ?? Term::opaque('INVALID_DOMAIN_FACT')), new DomainFact($domain->id(), $upper->operands['representation'] ?? Term::opaque('INVALID_DOMAIN_FACT')));
    }
}
