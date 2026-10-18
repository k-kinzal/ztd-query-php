<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Call\Provider;

/**
 * An explicitly installed, versioned source of analysis contracts.
 * @visibility root
 * @example Provider contracts are explicitly registered
 *     (new \Deriver\Deriver\Contributions())->providers // => []
 */
interface Provider
{
    /**
     * @return string Stable provider identity
     * @throws \Deriver\Exception\EvaluationException If trusted plugin code fails
     */
    public function id(): string;
    /**
     * @return string Semantic version for snapshot invalidation
     * @throws \Deriver\Exception\EvaluationException If trusted plugin code fails
     */
    public function version(): string;
}
