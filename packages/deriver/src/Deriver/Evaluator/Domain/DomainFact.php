<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Domain;

use Deriver\Deriver\Evaluator\Term;

/**
 * An immutable fact owned by a registered abstract domain.
 *
 * @visibility root
 * @example Inspecting the contract
 *     (new \Deriver\Deriver\Evaluator\Domain\DomainFact('policy', \Deriver\Deriver\Evaluator\Term::array([])))->domain // => 'policy'
 */
final class DomainFact
{
    /**
     * @param string $domain domain
     * @param Term $representation representation
     */
    public function __construct(
        public readonly string $domain,
        public readonly Term $representation,
    ) {
    }
    /**
     * Stores an immutable domain fact in the ordinary shared value graph.
     * @return Term Opaque-to-core, losslessly serializable domain value
     */
    public function term(): Term
    {
        return new Term(\Deriver\Deriver\Evaluator\TermKind::Domain, $this->domain, ['representation' => $this->representation]);
    }
}
