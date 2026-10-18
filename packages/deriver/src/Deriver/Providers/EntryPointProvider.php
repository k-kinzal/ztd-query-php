<?php

declare(strict_types=1);

namespace Deriver\Deriver\Providers;

/**
 * Provides explicit application entries and their initial arguments.
 * @visibility root
 * @example Providers are installed explicitly
 *     (new \Deriver\Deriver\Contributions())->providers // => []
 */
interface EntryPointProvider extends \Deriver\Deriver\Evaluator\Call\Provider\Provider
{
    /**
     * @return list<\Deriver\Deriver\Evaluator\Request\EntryPoint> Captured provider contribution
     * @throws \Deriver\Exception\EvaluationException If trusted plugin code fails
     */
    public function entries(): array;
}
