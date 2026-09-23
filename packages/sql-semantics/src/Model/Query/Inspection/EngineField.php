<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection;

use SqlSemantics\Type\Nullability;

/**
 * Storage-engine capabilities exposed by SHOW ENGINES.
 * @visibility public
 * @example Inspecting a result role
 *     \SqlSemantics\Model\Query\Inspection\EngineField::Name->value // => 'Engine'
 */
enum EngineField: string
{
    case Name = 'Engine';
    case Support = 'Support';
    case Description = 'Comment';
    case Transactions = 'Transactions';
    case Xa = 'XA';
    case Savepoints = 'Savepoints';

    /**
     * Returns the NULL fact declared for this metadata field.
     */
    public function nullability(): Nullability
    {
        return match ($this) {
            self::Transactions, self::Xa, self::Savepoints => Nullability::MaybeNull,
            self::Name, self::Support, self::Description => Nullability::NotNull,
        };
    }
}
