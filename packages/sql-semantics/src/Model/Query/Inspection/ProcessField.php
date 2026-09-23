<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection;

use SqlSemantics\Type\Nullability;

/**
 * Connection identity and activity metadata, preceding the active query text.
 * @visibility public
 * @example Identifying the connection
 *     \SqlSemantics\Model\Query\Inspection\ProcessField::Connection->value // => 'Id'
 */
enum ProcessField: string
{
    case Connection = 'Id';
    case User = 'User';
    case Host = 'Host';
    case Database = 'db';
    case Command = 'Command';
    case ElapsedTime = 'Time';
    case State = 'State';

    /**
     * Returns the NULL fact of the process metadata field.
     */
    public function nullability(): Nullability
    {
        return match ($this) {
            self::Database, self::State => Nullability::MaybeNull,
            self::Connection, self::User, self::Host, self::Command, self::ElapsedTime => Nullability::NotNull,
        };
    }
}
