<?php

declare(strict_types=1);

namespace Deriver\Model\Provider;

/**
 * Registers immutable domain facts with checked lattice contracts.
 * @visibility public
 * @example Providers are installed explicitly
 *     (new \Deriver\Project\Configuration())->providers // => []
 */
interface DomainProvider extends Provider
{
    /**
     * @return list<\Deriver\Model\Domain\AbstractDomain> Captured provider contribution
     * @throws \Deriver\Exception\ModelException If trusted plugin code fails
     */
    public function domains(): array;
}
