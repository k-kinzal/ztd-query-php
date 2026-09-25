<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Extension\Model;

use SqlCatalog\Core\Evaluation\Domain;

/**
 * Modelled SQL and bindings, retaining holes and uncertainty instead of flattening to strings.
 *
 * @visibility public
 *
 * @example Preserving an unresolved SQL fragment
 *     $sql = \SqlCatalog\Core\Evaluation\Domain::literal('SELECT * FROM ')->concat(\SqlCatalog\Core\Evaluation\Domain::unknown('table'));
 *     $output = new \SqlCatalog\Core\Extension\Model\QueryOutput($sql, \SqlCatalog\Core\Evaluation\Domain::literal(null));
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
