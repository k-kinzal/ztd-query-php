<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction\Xml;

/**
 * The explicit operands of an XMLTABLE ordinality declaration.
 * @visibility public
 */
final class Ordinality
{
    /**
     * Retains semantic input expressions and declaration options.
     */
    public function __construct(public readonly string $name)
    {
    }
}
