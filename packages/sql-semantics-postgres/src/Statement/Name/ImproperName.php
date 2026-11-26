<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Name;

use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A dotted name with more parts than the object it must name can have.
 *
 * Source: https://www.postgresql.org/docs/17/sql-syntax-lexical.html#SQL-SYNTAX-IDENTIFIERS.
 *
 * @visibility SqlSemantics
 */
final class ImproperName implements Diagnostic
{
    use Snapshot;

    /**
     * @param DottedName $name The name as written
     */
    public function __construct(public readonly DottedName $name)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        $parts = [];
        foreach ($this->name->parts as $part) {
            $parts[] = $part->value;
        }

        return 'Improper qualified name (too many dotted names): ' . implode('.', $parts) . '.';
    }
}
