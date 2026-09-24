<?php

declare(strict_types=1);

namespace SqlCatalog\Extension\Model;

use SqlCatalog\Evaluation\Domain;

/**
 * Modelled SQL and bindings, retaining holes and uncertainty instead of flattening to strings.
 *
 * @visibility public
 *
 * @example Preserving an unresolved SQL fragment
 *     $sql = \SqlCatalog\Evaluation\Domain::literal('SELECT * FROM ')->concat(\SqlCatalog\Evaluation\Domain::unknown('table'));
 *     $output = new \SqlCatalog\Extension\Model\QueryOutput($sql, \SqlCatalog\Evaluation\Domain::literal(null));
 *     $output->sql->isExact() // => false
 */
final class QueryOutput
{
    /**
     * The core adds caller, branch and budget evidence from the input derivation.
     */
    public function __construct(public readonly Domain $sql, public readonly Domain $bindings, public readonly bool $truncated = false, public readonly bool $combined = false)
    {
    }
}
