<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Call;

use Deriver\Deriver\Evaluator\Term;

/**
 * One signature-normalized argument and its optional reference-capable evaluator location.
 * @visibility root
 * @example Describing a supplied argument
 *     (new \Deriver\Deriver\Evaluator\Call\BoundArgument('id', \Deriver\Deriver\Evaluator\Term::constant(2), supplied: true))->supplied // => true
 */
final class BoundArgument
{
    /**
     * @param string $name Normalized signature parameter name
     * @param Term $value Abstract value before parameter type coercion, or a formal parameter during preparation
     * @param Plan\Location\LocationRef|null $location Writable formal storage for a declared reference parameter
     * @param bool|null $supplied Whether explicitly supplied; null during signature preparation
     * @param bool $variadic Whether the value collects the variadic argument array
     */
    public function __construct(public readonly string $name, public readonly Term $value, public readonly ?Plan\Location\LocationRef $location = null, public readonly ?bool $supplied = null, public readonly bool $variadic = false)
    {
    }
}
