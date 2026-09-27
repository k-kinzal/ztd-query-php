<?php

declare(strict_types=1);

namespace Deriver\Model\Domain;

use Deriver\Value\Term;

/**
 * An immutable fact owned by a registered abstract domain.
 *
 * @visibility public
 * @example Inspecting the contract
 *     (new \Deriver\Model\Domain\DomainFact('policy', \Deriver\Value\Term::array([])))->domain // => 'policy'
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
        return new Term('domain', $this->domain, ['representation' => $this->representation]);
    }
}
