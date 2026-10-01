<?php

declare(strict_types=1);

namespace SqlSemantics\Semantic\Type;

/**
 * The external information needed to determine a type.
 * @example Reading semantic relationships
 *     \SqlSemantics\Semantic\Type\UnknownReason::CatalogNotSupplied->value // => 'catalog-not-supplied'
 *
 * @visibility public
 */
enum UnknownReason: string
{
    case CatalogNotSupplied = 'catalog-not-supplied';
    case ParameterNotSupplied = 'parameter-not-supplied';
    case NullLiteral = 'null-literal';
}
