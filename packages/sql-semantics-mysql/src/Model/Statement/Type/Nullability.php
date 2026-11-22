<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Statement\Type;

/**
 * Whether a value can be SQL NULL at its position.
 *
 * `Dependent` means the answer needs information missing from the context;
 * consumers must then treat the value as possibly NULL.
 *
 * @visibility SqlSemantics
 */
enum Nullability
{
    case NotNull;
    case Nullable;
    case Dependent;

    /**
     * Combines the facts of operands when NULL in either operand yields NULL.
     */
    public function propagate(self $other): self
    {
        if ($this === self::Nullable || $other === self::Nullable) {
            return self::Nullable;
        }

        return $this === self::Dependent || $other === self::Dependent ? self::Dependent : self::NotNull;
    }
}
