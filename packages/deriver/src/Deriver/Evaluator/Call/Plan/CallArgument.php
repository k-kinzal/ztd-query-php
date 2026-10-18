<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Call\Plan;

use Deriver\Deriver\Evaluator\Call\Plan\Location\LocationRef;

/**
 * An ordered evaluator-call argument with its name, unpack mode, and optional storage reference.
 * @visibility root
 * @example Passing a named reference-capable argument
 *     (new \Deriver\Deriver\Evaluator\Call\Plan\CallArgument(\Deriver\Deriver\Evaluator\Call\Plan\Location\LocationRef::parameter('value'), 'item'))->name // => 'item'
 */
final class CallArgument
{
    /**
     * @param Expression|LocationRef $value \Deriver\Deriver\Evaluator\Call\Plan\Expression or address whose passing mode is selected by the callee signature
     * @param string|null $name Named parameter spelling, or null for positional passing
     * @param bool $unpack Whether the array or iterator is expanded
     */
    public function __construct(public readonly Expression|LocationRef $value, public readonly ?string $name = null, public readonly bool $unpack = false)
    {
    }
}
