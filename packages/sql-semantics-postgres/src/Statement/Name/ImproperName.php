<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Name;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A dotted name with more parts than the object it must name can have.
 *
 * Source: https://www.postgresql.org/docs/17/sql-syntax-lexical.html#SQL-SYNTAX-IDENTIFIERS.
 *
 * @visibility public
 * @example Reporting a type name with four parts
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("SELECT a.b.c.d '1'");
 *     $query->field(0)->type->cause->message() // => 'Improper qualified name (too many dotted names): a.b.c.d.'
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
