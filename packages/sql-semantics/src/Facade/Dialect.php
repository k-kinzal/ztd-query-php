<?php

declare(strict_types=1);

namespace SqlSemantics\Facade;

/**
 * The database language whose binding and type rules apply.
 *
 * @example Reading semantic facts
 *     \SqlSemantics\Facade\Dialect::PostgreSql->value // => 'postgresql'
 *
 * @visibility public
 */
enum Dialect: string implements \SqlSemantics\Core\Dialect
{
    case PostgreSql = 'postgresql';
    case MySql = 'mysql';
    case Sqlite = 'sqlite';
    /**
     * Composes the selected built-in semantic implementation.
     */
    public function platform(): \SqlSemantics\Core\Platform
    {
        return match ($this) {
            self::MySql => new \SqlSemantics\Platform\MySql\Platform($this),
            self::PostgreSql => new \SqlSemantics\Platform\PostgreSql\Platform($this),
            self::Sqlite => new \SqlSemantics\Platform\Sqlite\Platform($this),
        };
    }
}
