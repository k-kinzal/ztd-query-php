<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Transaction\MySql;

/**
 * A requested MySQL transaction access mode, including the grammar's contradictory combination.
 * @visibility public
 * @example Distinguishing an omitted mode from an explicit write request
 *     \SqlSemantics\Statement\Transaction\MySql\Access::SessionDefault->toString() // => ''
 */
enum Access
{
    case SessionDefault;
    case ReadOnly;
    case ReadWrite;
    case Conflicting;

    /**
     * Reconstructs both contradictory requirements so analysis does not silently repair the request.
     */
    public function toString(): string
    {
        return match ($this) {
            self::SessionDefault => '',
            self::ReadOnly => 'READ ONLY',
            self::ReadWrite => 'READ WRITE',
            self::Conflicting => 'READ ONLY, READ WRITE',
        };
    }
}
