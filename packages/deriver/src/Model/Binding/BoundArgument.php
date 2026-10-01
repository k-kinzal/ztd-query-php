<?php

declare(strict_types=1);

namespace Deriver\Model\Binding;

use Deriver\Value\Term;

/**
 * One signature-normalized argument and its optional reference-capable model location.
 * @visibility public
 * @example Describing a supplied argument
 *     (new \Deriver\Model\Binding\BoundArgument('id', \Deriver\Value\Term::constant(2), supplied: true))->supplied // => true
 */
final class BoundArgument
{
    /**
     * @param string $name Normalized signature parameter name
     * @param Term $value Abstract value before parameter type coercion, or a formal parameter during preparation
     * @param LocationRef|null $location Writable formal storage for a declared reference parameter
     * @param bool|null $supplied Whether explicitly supplied; null during signature preparation
     * @param bool $variadic Whether the value collects the variadic argument array
     */
    public function __construct(public readonly string $name, public readonly Term $value, public readonly ?LocationRef $location = null, public readonly ?bool $supplied = null, public readonly bool $variadic = false)
    {
    }
}
