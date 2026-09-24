<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Catalog\Kind;

/**
 * Operator classes and operator families, both addressed by a name and an index access method.
 * @visibility public
 * @example Reading the SQL spelling of an operator family
 *     \SqlSemantics\Model\Definition\Catalog\Kind\OperatorSetKind::OperatorFamily->value // => 'OPERATOR FAMILY'
 */
enum OperatorSetKind: string
{
    case OperatorClass = 'OPERATOR CLASS';
    case OperatorFamily = 'OPERATOR FAMILY';
}
