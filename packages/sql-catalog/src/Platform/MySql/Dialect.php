<?php

declare(strict_types=1);

namespace SqlCatalog\Platform\MySql;

use SqlCatalog\Core\Sql\Dialect as Contract;

/**
 * MySql identifier and insert spelling.
 *
 * @visibility root
 */
final class Dialect implements Contract
{
    /**
     * The identifier delimiter.
     */
    public function identifierQuote(): string
    {
        return '`';
    }

    /**
     * The insert form, with optional conflict handling.
     */
    public function insertPrefix(bool $ignore): string
    {
        return $ignore ? 'insert ignore into ' : 'insert into ';
    }

    /**
     * The trailing conflict clause.
     */
    public function insertSuffix(bool $ignore): string
    {
        return '';
    }
}
