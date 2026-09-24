<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Catalog\Kind;

/**
 * The PostgreSQL routine classes that share the overloaded signature address form.
 * @visibility public
 * @example Reading the SQL spelling of the generic routine class
 *     \SqlSemantics\Model\Definition\Catalog\Kind\RoutineKind::Routine->value // => 'ROUTINE'
 */
enum RoutineKind: string
{
    case Function = 'FUNCTION';
    case Procedure = 'PROCEDURE';
    case Routine = 'ROUTINE';
}
