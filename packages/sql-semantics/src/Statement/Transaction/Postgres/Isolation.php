<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Transaction\Postgres;

/**
 * The requested PostgreSQL isolation level, retaining READ UNCOMMITTED as its own setting.
 * @visibility public
 * @example Inspecting a requested isolation setting
 *     \SqlSemantics\Statement\Transaction\Postgres\Isolation::RepeatableRead->value // => 'REPEATABLE READ'
 */
enum Isolation: string
{
    case ReadUncommitted = 'READ UNCOMMITTED';
    case ReadCommitted = 'READ COMMITTED';
    case RepeatableRead = 'REPEATABLE READ';
    case Serializable = 'SERIALIZABLE';
}
