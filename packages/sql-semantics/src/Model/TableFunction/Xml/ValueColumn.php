<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction\Xml;

/**
 * The explicit operands of an XMLTABLE valuecolumn declaration.
 * @visibility public
 */
final class ValueColumn
{
    /**
     * Retains semantic input expressions and declaration options.
     */
    public function __construct(public readonly string $name, public readonly \SqlSemantics\Type\TypeDescriptor $type, public readonly ?\SqlSemantics\Model\Expression $path = null, public readonly ?\SqlSemantics\Model\Expression $default = null, public readonly bool $notNull = false)
    {
    }
}
