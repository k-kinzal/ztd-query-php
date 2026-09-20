<?php

declare(strict_types=1);

namespace SqlSemantics\Type;

/**
 * A conservative fact about possible SQL NULL results, independent of PHP null.
 *
 * @example Reading semantic facts
 *     \SqlSemantics\Type\Nullability::Unknown->value // => 'unknown'
 *
 * @visibility public
 */
enum Nullability: string
{
    case NotNull = 'not-null';
    case MaybeNull = 'maybe-null';
    case AlwaysNull = 'always-null';
    case Unknown = 'unknown';
}
