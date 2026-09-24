<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Privilege\PostgreSql\Target;

/**
 * Object classes addressed by an unqualified name in privilege operations.
 * @visibility public
 * @example Inspecting the class
 *     \SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\ServerObjectClass::ForeignDataWrapper->value // => 'FOREIGN DATA WRAPPER'
 */
enum ServerObjectClass: string
{
    case Database = 'DATABASE';
    case ForeignDataWrapper = 'FOREIGN DATA WRAPPER';
    case ForeignServer = 'FOREIGN SERVER';
    case Language = 'LANGUAGE';
    case Schema = 'SCHEMA';
    case Tablespace = 'TABLESPACE';
}
