<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Semantics;

use SqlSemantics\Statement\Source\Span;

/**
 * An enumerated keyword has no independent binding or occurrence location.
 * Its containing SQL clause owns the location in the input.
 *
 * @visibility SqlSemantics
 */
trait Keyword
{
    /**
 * Returns no independent source range for a shared keyword value.
 */
    public function source(): ?Span
    {
        return null;
    }

    /**
 * Keywords introduce no value, relation or query binding.
 */
    public function hasBinding(): bool
    {
        return false;
    }
}
