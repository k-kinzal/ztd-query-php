<?php

declare(strict_types=1);

namespace Deriver\Deriver\Providers;

use Deriver\Deriver\Sources\Sources;

/**
 * Provides captured declarations as data; Source::declarationsOnly keeps signatures separate from bodies.
 * @visibility root
 * @example Providers are installed explicitly
 *     (new \Deriver\Deriver\Contributions())->providers // => []
 */
interface DeclarationProvider extends \Deriver\Deriver\Evaluator\Call\Provider\Provider
{
    /**
     * @return Sources Captured source or signature-only provider contribution
     * @throws \Deriver\Exception\EvaluationException If trusted plugin code fails
     */
    public function declarations(): Sources;
}
