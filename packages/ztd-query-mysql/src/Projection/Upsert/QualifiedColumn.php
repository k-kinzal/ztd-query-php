<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Projection\Upsert;

use ZtdQuery\Platform\IdentifierQuoter;

/**
 * Qualified Column.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class QualifiedColumn
{
    /**
     * Configure the dependencies used by this operation.
     */
    public function __construct(private IdentifierQuoter $quoter)
    {
    }
    /**
     * Qualified for the supplied MySQL input.
     */
    public function qualified(string $alias, string $column): string
    {
        return $this->quoter->quote($alias) . '.' . $this->quoter->quote($column);
    }
}
