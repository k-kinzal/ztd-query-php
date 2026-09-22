<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction\Xml;

/**
 * The explicit operands of an XMLTABLE namespacebinding declaration.
 * @visibility public
 */
final class NamespaceBinding
{
    /**
     * Retains semantic input expressions and declaration options.
     */
    public function __construct(public readonly \SqlSemantics\Model\Expression $uri, public readonly ?string $prefix = null)
    {
    }
}
