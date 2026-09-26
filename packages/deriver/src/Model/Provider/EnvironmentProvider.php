<?php

declare(strict_types=1);

namespace Deriver\Model\Provider;

/**
 * Provides a captured environment; the core never reads the host environment.
 * @visibility public
 * @example Providers are installed explicitly
 *     (new \Deriver\Api\Project\Configuration())->providers // => []
 */
interface EnvironmentProvider extends Provider
{
    /**
     * @return array<string, \Deriver\Value\Term> Captured provider contribution
     * @throws \Deriver\Model\ModelException If trusted plugin code fails
     */
    public function environment(): array;
}
