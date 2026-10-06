<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules;

use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Statement\Identifier\Name;

/**
 * The current database of the session a context describes.
 *
 * Rule: MYSQL-CURRENT-DATABASE-001. An unqualified table name is searched in
 * one database, the current database of the session (the first and only
 * entry of the context's search path). When the caller names none, the
 * platform searches the placeholder name UNNAMED, which stands for a session
 * whose current database is not known: rules that would print or compare
 * the real name of the current database (SHOW TABLES column names, the
 * database of a privilege on `*`) treat it as unknown instead. Terminates:
 * one comparison.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/identifier-qualifiers.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SessionDatabase
{
    /**
     * The database an unqualified name belongs to when the caller names no current database.
     */
    public const UNNAMED = '(current)';

    /**
     * Answers the current database a context names, or null when the caller named none.
     */
    public function named(AnalysisContext $context): ?Name
    {
        $current = $context->searchPath[0];

        return $current->value === self::UNNAMED ? null : $current;
    }
}
