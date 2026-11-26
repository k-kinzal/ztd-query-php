<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\From;

/**
 * The two nullability roles of a native signed integer input slot.
 *
 * @visibility public
 * @example Reading the signed storage name
 *     \SqlSemantics\Platform\MySql\Ir\From\ColumnKind::SignedInt->value // => 'int'
 */
enum ColumnKind: string
{
    case SignedInt = 'int';
    case RequiredSignedInt = 'int-not-null';

    /**
     * Gives the nullability of the resolved signed slot.
     */
    public function nullable(): bool
    {
        return match ($this) {
            self::SignedInt => true,
            self::RequiredSignedInt => false,
        };
    }
}
