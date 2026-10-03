<?php

declare(strict_types=1);

namespace SqlSemantics\Core;

/**
 * Whether the dependencies a statement is analyzed against declare every table of the database, or only some of them.
 *
 * With complete declarations, a table name that resolves to nothing is a
 * missing table. With partial declarations, the database may have tables
 * the dependencies do not describe, and such a reference lacks declaration
 * information about its columns. Context operations never alter or remove
 * declarations: the dependency list is not an execution history.
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
