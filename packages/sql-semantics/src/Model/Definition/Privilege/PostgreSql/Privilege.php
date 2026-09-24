<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Privilege\PostgreSql;

/**
 * A PostgreSQL privilege type; TEMP binds as TEMPORARY and ALL PRIVILEGES stands alone.
 * @visibility public
 * @example Inspecting a privilege
 *     \SqlSemantics\Model\Definition\Privilege\PostgreSql\Privilege::AlterSystem->value // => 'ALTER SYSTEM'
 */
enum Privilege: string
{
    case Select = 'SELECT';
    case Insert = 'INSERT';
    case Update = 'UPDATE';
    case Delete = 'DELETE';
    case Truncate = 'TRUNCATE';
    case References = 'REFERENCES';
    case Trigger = 'TRIGGER';
    case Execute = 'EXECUTE';
    case Usage = 'USAGE';
    case Create = 'CREATE';
    case Connect = 'CONNECT';
    case Temporary = 'TEMPORARY';
    case Set = 'SET';
    case AlterSystem = 'ALTER SYSTEM';
    case Maintain = 'MAINTAIN';
    case All = 'ALL PRIVILEGES';

    /**
     * Reports whether the privilege can be restricted to table columns.
     */
    public function columnar(): bool
    {
        return in_array($this, [self::Select, self::Insert, self::Update, self::References, self::All], true);
    }
}
