<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Catalog\Kind;

/**
 * Distinguishes base and composite types from domains in type-addressed commands.
 * @visibility public
 * @example Reading the SQL spelling of the domain class
 *     \SqlSemantics\Model\Definition\Catalog\Kind\TypeKind::Domain->value // => 'DOMAIN'
 */
enum TypeKind: string
{
    case Type = 'TYPE';
    case Domain = 'DOMAIN';
}
