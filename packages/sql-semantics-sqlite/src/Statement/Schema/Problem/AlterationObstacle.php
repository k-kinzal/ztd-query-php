<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Problem;

/**
 * The reasons SQLite refuses to add or drop a column.
 *
 * Only the reasons that follow from the statement and the declared columns
 * are modeled; those that need the constraints and indexes of the existing
 * table are not part of a declaration context.
 * Source: https://sqlite.org/lang_altertable.html.
 *
 * @visibility public
 * @example Naming the obstacle of adding a primary key column
 *     $alter = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('ALTER TABLE t ADD COLUMN id INTEGER PRIMARY KEY');
 *     $alter->facts->diagnostics[0]->obstacle // => \SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\AlterationObstacle::PrimaryKeyColumn
 */
enum AlterationObstacle: string
{
    case PrimaryKeyColumn = 'A PRIMARY KEY column cannot be added.';
    case UniqueColumn = 'A UNIQUE column cannot be added.';
    case StoredColumn = 'A STORED generated column cannot be added.';
    case NotNullWithoutDefault = 'A NOT NULL column cannot be added without a default value.';
    case DefaultNotConstant = 'A column cannot be added with a default value that is not a literal.';
    case LastColumn = 'The only column of a table cannot be dropped.';
}
