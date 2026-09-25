<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Sql;

/**
 * What a catalogued statement does to the database.
 *
 * @visibility root
 */
enum StatementKind: string
{
    case Select = 'select';
    case Insert = 'insert';
    case Update = 'update';
    case Delete = 'delete';
    case Replace = 'replace';
    case Merge = 'merge';
    case Truncate = 'truncate';
    case Create = 'create';
    case Alter = 'alter';
    case Drop = 'drop';
    case Call = 'call';
    case Show = 'show';
    case Explain = 'explain';
    case Transaction = 'transaction';
    case Other = 'other';
    case Unknown = 'unknown';

    /**
     * Whether the statement can change stored rows.
     */
    public function isWrite(): bool
    {
        return match ($this) {
            self::Insert, self::Update, self::Delete, self::Replace, self::Merge, self::Truncate => true,
            self::Select, self::Create, self::Alter, self::Drop, self::Call, self::Show,
            self::Explain, self::Transaction, self::Other, self::Unknown => false,
        };
    }

    /**
     * Whether the statement changes the schema rather than the rows.
     */
    public function isSchema(): bool
    {
        return match ($this) {
            self::Create, self::Alter, self::Drop, self::Truncate => true,
            self::Select, self::Insert, self::Update, self::Delete, self::Replace, self::Merge,
            self::Call, self::Show, self::Explain, self::Transaction, self::Other, self::Unknown => false,
        };
    }
}
