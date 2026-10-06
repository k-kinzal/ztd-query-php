<?php

declare(strict_types=1);

namespace Deriver\Model\Provider;

use Deriver\AnalysisSession;
use Deriver\Query\Query;

/**
 * Produces queries from a captured session without changing language semantics.
 * @visibility public
 * @example Queries remain ordinary public API values
 *     (new \Deriver\Query\ReturnQuery('run'))->symbol // => 'run'
 */
interface ObservationProvider extends Provider
{
    /**
     * @param AnalysisSession $session Captured project and call-site index
     * @return array<string, Query> Named independent queries
     * @throws \Deriver\Exception\ModelException If trusted plugin code fails
     */
    public function queries(AnalysisSession $session): array;
}
