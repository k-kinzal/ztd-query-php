<?php

declare(strict_types=1);

namespace Deriver\Deriver\Candidate;

/**
 * A stable ResultRef within a analysis snapshot.
 *
 * @visibility public
 * @example Creating a reference
 *     (new \Deriver\Deriver\Candidate\ResultRef('query'))->id // => 'query'
 */
final class ResultRef
{
    /**
     * @param string $id Stable query result identity
     */
    public function __construct(
        public readonly string $id,
    ) {
    }
}
