<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Sql;

/**
 * SQL spelling needed by framework query models.
 *
 * @visibility root
 */
interface Dialect
{
    /**
     * The delimiter used for a quoted identifier.
     */
    public function identifierQuote(): string;

    /**
     * The opening of an insert, optionally ignoring conflicting rows.
     */
    public function insertPrefix(bool $ignore): string;

    /**
     * The trailing conflict clause, if any.
     */
    public function insertSuffix(bool $ignore): string;

    /**
     * The clause introducing the generated key an insert returns, or empty when the driver reads it afterwards.
     */
    public function returningSuffix(): string;
}
