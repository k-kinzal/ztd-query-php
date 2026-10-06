<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege;

/**
 * The privileges the grammar spells with keywords; the value of a case is its keywords.
 *
 * Every other privilege is written as an identifier. ALL stands for every
 * privilege of the object and is written with a column list only: without
 * one, the privilege list of the statement is empty.
 * Source: https://www.postgresql.org/docs/17/sql-grant.html.
 *
 * @visibility public
 * @example Reading the privilege name the server receives
 *     \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\PrivilegeKeyword::AlterSystem->privilege() // => 'alter system'
 */
enum PrivilegeKeyword: string
{
    case All = 'ALL';
    case Select = 'SELECT';
    case References = 'REFERENCES';
    case Create = 'CREATE';
    case AlterSystem = 'ALTER SYSTEM';

    /**
     * Answers the privilege name the server receives, or null for ALL, which names none.
     */
    public function privilege(): ?string
    {
        return $this === self::All ? null : strtolower($this->value);
    }
}
