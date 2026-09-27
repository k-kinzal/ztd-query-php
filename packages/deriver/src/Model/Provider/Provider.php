<?php

declare(strict_types=1);

namespace Deriver\Model\Provider;

/**
 * An explicitly installed, versioned source of analysis contracts.
 * @visibility public
 * @example Provider contracts are explicitly registered
 *     (new \Deriver\Project\Configuration())->providers // => []
 */
interface Provider
{
    /**
     * @return string Stable provider identity
     * @throws \Deriver\Exception\ModelException If trusted plugin code fails
     */
    public function id(): string;
    /**
     * @return string Semantic version for snapshot invalidation
     * @throws \Deriver\Exception\ModelException If trusted plugin code fails
     */
    public function version(): string;
}
