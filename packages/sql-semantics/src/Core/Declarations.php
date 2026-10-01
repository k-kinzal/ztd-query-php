<?php

declare(strict_types=1);

namespace SqlSemantics\Core;

/**
 * Whether the dependencies a statement is analyzed against declare every table of the database, or only some of them.
 *
 * With complete declarations, a table name that resolves to nothing is an
 * error: the dependency that would declare it was not given. With partial
 * declarations, the database has tables the dependencies do not describe,
 * and such a name is a reference to an undeclared table, with nothing known
 * of its columns, unless the dependencies dropped that table; every other
 * name is resolved as before.
 *
 * @visibility public
 * @example Reading declarations that describe only some tables
 *     \SqlSemantics\Core\Declarations::Partial->name // => 'Partial'
 */
enum Declarations
{
    /**
     * The dependencies declare every table the statement can name.
     */
    case Complete;

    /**
     * The database may have tables no dependency declares.
     */
    case Partial;
}
