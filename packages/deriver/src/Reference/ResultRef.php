<?php

declare(strict_types=1);

namespace Deriver\Reference;

/**
 * A stable ResultRef within a project snapshot.
 *
 * @visibility public
 * @example Creating a reference
 *     (new \Deriver\Reference\ResultRef('query'))->id // => 'query'
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
