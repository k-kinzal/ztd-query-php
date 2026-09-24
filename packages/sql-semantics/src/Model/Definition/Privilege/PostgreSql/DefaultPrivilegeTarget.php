<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Privilege\PostgreSql;

/**
 * The object class whose future objects receive default privileges; ROUTINES binds as FUNCTIONS.
 * @visibility public
 * @example Inspecting the class
 *     \SqlSemantics\Model\Definition\Privilege\PostgreSql\DefaultPrivilegeTarget::Tables->value // => 'TABLES'
 */
enum DefaultPrivilegeTarget: string
{
    case Tables = 'TABLES';
    case Sequences = 'SEQUENCES';
    case Functions = 'FUNCTIONS';
    case Types = 'TYPES';
    case Schemas = 'SCHEMAS';
}
