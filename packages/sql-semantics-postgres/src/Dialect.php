<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql;

/**
 * Selects PostgreSQL for semantic analysis.
 *
 * @visibility public
 */
enum Dialect: string implements \SqlSemantics\Contract\Dialect
{
    case PostgreSql = 'postgresql';

    /**
     * Names the database family as the grammar releases do.
     */
    public function database(): string
    {
        return $this->value;
    }
}
