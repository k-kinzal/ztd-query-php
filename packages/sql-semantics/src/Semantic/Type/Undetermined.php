<?php

declare(strict_types=1);

namespace SqlSemantics\Semantic\Type;

/**
 * A fact that cannot be determined from the supplied inputs.
 * @example Reading semantic relationships
 *     $type = new \SqlSemantics\Semantic\Type\Undetermined(\SqlSemantics\Semantic\Type\UnknownReason::ParameterNotSupplied);
 *     $type->reason->value // => 'parameter-not-supplied'
 *
 * @visibility public
 */
final class Undetermined
{
    /**
     * The semantic name exposed by this value.
     */
    public readonly string $name;

    /**
     * Constructs the value and asserts the relationships required by its fields.
     */
    public function __construct(public readonly UnknownReason $reason)
    {
        $this->name = 'unknown';
    }
}
