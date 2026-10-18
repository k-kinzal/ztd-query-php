<?php

declare(strict_types=1);

namespace Deriver\Deriver\Providers;

/**
 * Registers immutable domain facts with checked lattice contracts.
 * @visibility root
 * @example Providers are installed explicitly
 *     (new \Deriver\Deriver\Contributions())->providers // => []
 */
interface DomainProvider extends \Deriver\Deriver\Evaluator\Call\Provider\Provider
{
    /**
     * @return list<\Deriver\Deriver\Evaluator\Domain\AbstractDomain> Captured provider contribution
     * @throws \Deriver\Exception\EvaluationException If trusted plugin code fails
     */
    public function domains(): array;
}
