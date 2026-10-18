<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Call\Plan\Location;

use Deriver\Deriver\Evaluator\Call\Plan\Expression;

/**
 * A declarative reference to evaluator storage, resolved by the core after argument binding.
 * @visibility root
 * @example Naming a reference parameter
 *     \Deriver\Deriver\Evaluator\Call\Plan\Location\LocationRef::parameter('value')->name // => 'value'
 */
final class LocationRef
{
    /**
     * @param LocationKind $kind Parameter, state slot, or array element
     * @param string $name Parameter binding or namespaced state slot
     * @param Expression|null $receiver Receiver of a state slot
     * @param self|null $parent Containing array location
     * @param Expression|null $key Array key; omitted for append
     */
    public function __construct(public readonly LocationKind $kind, public readonly string $name = '', public readonly ?Expression $receiver = null, public readonly ?self $parent = null, public readonly ?Expression $key = null)
    {
    }

    /**
     * Refers to a normalized parameter or a local result from a preceding action.
     * @param string $name Binding name
     * @return self Parameter or result location
     */
    public static function parameter(string $name): self
    {
        return new self(LocationKind::Parameter, $name);
    }

    /**
     * Refers to an abstract object state slot without exposing solver memory identifiers.
     * @param string $slot Namespaced slot
     * @param Expression|null $receiver Bound object; omitted means this
     * @return self Object slot location
     */
    public static function state(string $slot, ?Expression $receiver = null): self
    {
        return new self(LocationKind::State, $slot, $receiver ?? Expression::receiver());
    }

    /**
     * Refers to an array element using the same key and reference semantics as PHP source.
     * @param self $array Array location
     * @param Expression|null $key Element key; omitted for append
     * @return self Array element location
     */
    public static function element(self $array, ?Expression $key = null): self
    {
        return new self(LocationKind::Element, parent: $array, key: $key);
    }
}
