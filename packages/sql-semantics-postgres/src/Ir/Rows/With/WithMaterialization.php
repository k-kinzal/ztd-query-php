<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Rows\With;

/**
 * The native materialization choices of an ordinary WITH definition.
 *
 * @visibility public
 * @example Preserving explicit materialization
 *     \SqlSemantics\Platform\PostgreSql\Ir\Rows\With\WithMaterialization::Materialized->value // => 'MATERIALIZED'
 */
enum WithMaterialization: string
{
    case Default = '';
    case Materialized = 'MATERIALIZED';
    case NotMaterialized = 'NOT MATERIALIZED';
}
