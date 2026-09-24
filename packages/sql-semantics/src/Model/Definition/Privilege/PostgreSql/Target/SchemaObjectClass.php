<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Privilege\PostgreSql\Target;

/**
 * Schema-qualified object classes addressed by name in privilege operations.
 * @visibility public
 * @example Inspecting the class
 *     \SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\SchemaObjectClass::Domain->value // => 'DOMAIN'
 */
enum SchemaObjectClass: string
{
    case Sequence = 'SEQUENCE';
    case Domain = 'DOMAIN';
    case Type = 'TYPE';
}
