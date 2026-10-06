<?php

declare(strict_types=1);

namespace Deriver\Model\Provider;

/**
 * Provides explicit application entries and their initial arguments.
 * @visibility public
 * @example Providers are installed explicitly
 *     (new \Deriver\Project\Configuration())->providers // => []
 */
interface EntryPointProvider extends Provider
{
    /**
     * @return list<\Deriver\Project\EntryPoint> Captured provider contribution
     * @throws \Deriver\Exception\ModelException If trusted plugin code fails
     */
    public function entries(): array;
}
