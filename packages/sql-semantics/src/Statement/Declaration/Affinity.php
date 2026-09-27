<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Declaration;

/**
 * The storage class a declared type prefers when a dialect coerces values on storage.
 *
 * Affinity is a preference derived from the declared type name, not a
 * guarantee about the storage class of a stored value.
 *
 * @example Reading semantic facts
 *     \SqlSemantics\Statement\Declaration\Affinity::Text->value // => 'text'
 *
 * @visibility public
 */
enum Affinity: string
{
    case Integer = 'integer';
    case Text = 'text';
    case Blob = 'blob';
    case Real = 'real';
    case Numeric = 'numeric';
}
