<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Expression;

/**
 * The effective conversion and expression affinity of a SQLite CAST target.
 *
 * @visibility public
 * @example Reading the canonical target
 *     \SqlSemantics\Platform\Sqlite\Ir\Expression\CastTarget::Numeric->value // => 'NUMERIC'
 */
enum CastTarget: string
{
    case Integer = 'INTEGER';
    case Real = 'REAL';
    case Numeric = 'NUMERIC';
    case Text = 'TEXT';
    case Blob = 'BLOB';
}
