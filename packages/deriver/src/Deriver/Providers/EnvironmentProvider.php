<?php

declare(strict_types=1);

namespace Deriver\Deriver\Providers;

/**
 * Provides a captured environment; the core never reads the host environment.
 * @visibility root
 * @example Providers are installed explicitly
 *     (new \Deriver\Deriver\Contributions())->providers // => []
 */
interface EnvironmentProvider extends \Deriver\Deriver\Evaluator\Call\Provider\Provider
{
    /**
     * @return array<string, \Deriver\Deriver\Evaluator\Term> Captured provider contribution
     * @throws \Deriver\Exception\EvaluationException If trusted plugin code fails
     */
    public function environment(): array;
}
