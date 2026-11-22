<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column;

/**
 * When a generated or identity column takes its generated value: always, or unless a value is given.
 *
 * Mirrors `generated_when` (`ATTRIBUTE_IDENTITY_ALWAYS`/`BY_DEFAULT`).
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html.
 *
 * @visibility SqlSemantics
 */
enum GeneratedWhen: string
{
    case Always = 'ALWAYS';
    case ByDefault = 'BY DEFAULT';

    /**
     * Answers the keywords.
     *
     * @return list<string>
     */
    public function keywords(): array
    {
        return explode(' ', $this->value);
    }
}
