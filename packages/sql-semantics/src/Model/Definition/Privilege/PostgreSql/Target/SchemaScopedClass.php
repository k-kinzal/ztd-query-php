<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Privilege\PostgreSql\Target;

/**
 * Object classes whose existing members in a schema are selected together.
 * @visibility public
 * @example Inspecting the class
 *     \SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\SchemaScopedClass::Sequences->value // => 'SEQUENCES'
 */
enum SchemaScopedClass: string
{
    case Tables = 'TABLES';
    case Sequences = 'SEQUENCES';
    case Functions = 'FUNCTIONS';
    case Procedures = 'PROCEDURES';
    case Routines = 'ROUTINES';
}
