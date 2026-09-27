<?php

declare(strict_types=1);

namespace Deriver\Model\Plan;

use Deriver\Model\Binding\LocationRef;

/**
 * An ordered model-call argument with its name, unpack mode, and optional storage reference.
 * @visibility public
 * @example Passing a named reference-capable argument
 *     (new \Deriver\Model\Plan\CallArgument(\Deriver\Model\Binding\LocationRef::parameter('value'), 'item'))->name // => 'item'
 */
final class CallArgument
{
    /**
     * @param Expression|LocationRef $value Expression or address whose passing mode is selected by the callee signature
     * @param string|null $name Named parameter spelling, or null for positional passing
     * @param bool $unpack Whether the array or iterator is expanded
     */
    public function __construct(public readonly Expression|LocationRef $value, public readonly ?string $name = null, public readonly bool $unpack = false)
    {
    }
}
