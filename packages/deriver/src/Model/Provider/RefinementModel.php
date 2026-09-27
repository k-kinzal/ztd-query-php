<?php

declare(strict_types=1);

namespace Deriver\Model\Provider;

use Deriver\Value\Term;

/**
 * Supplies an implied predicate under an explicitly observed condition.
 * @visibility public
 * @example Refinements use immutable symbolic expressions
 *     \Deriver\Value\Term::parameter('id', 'int')->kind // => 'parameter'
 */
interface RefinementModel extends Provider
{
    /**
     * @param Term $predicate Observed condition
     * @param bool $truth Required branch polarity
     * @return Term|null Additional guaranteed predicate, or no refinement
     * @throws \Deriver\Exception\ModelException If trusted plugin code fails
     */
    public function refine(Term $predicate, bool $truth): ?Term;
}
