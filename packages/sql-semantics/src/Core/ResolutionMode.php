<?php

declare(strict_types=1);

namespace SqlSemantics\Core;

/**
 * Whether absent dependencies are errors or explicit unresolved references.
 *
 * @example Reading the typed value
 *     \SqlSemantics\Core\ResolutionMode::Partial->name // => 'Partial'
 * @visibility public
 */
enum ResolutionMode
{
    case Strict;
    case Partial;
}
