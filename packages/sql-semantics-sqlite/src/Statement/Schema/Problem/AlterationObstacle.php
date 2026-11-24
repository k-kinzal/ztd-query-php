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
 * @visibility SqlSemantics
 */
enum AlterationObstacle: string
{
    case PrimaryKeyColumn = 'A PRIMARY KEY column cannot be added.';
    case UniqueColumn = 'A UNIQUE column cannot be added.';
    case LastColumn = 'The only column of a table cannot be dropped.';
}
