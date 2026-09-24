<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Foreign;

/**
 * One INCLUDING or EXCLUDING choice of a LIKE clause; later choices override earlier ones.
 * @visibility public
 * @example Reading an exclusion
 *     $selection = new \SqlSemantics\Model\Definition\Relation\Foreign\TemplateSelection(\SqlSemantics\Model\Definition\Relation\Foreign\TemplateProperty::Indexes, false);
 *     $selection->including // => false
 */
final class TemplateSelection
{
    /**
     * The property and its direction are the complete operands.
     */
    public function __construct(public readonly TemplateProperty $property, public readonly bool $including)
    {
    }
}
