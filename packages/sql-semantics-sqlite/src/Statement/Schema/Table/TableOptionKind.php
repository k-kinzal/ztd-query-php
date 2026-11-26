<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Table;

/**
 * The table options SQLite knows.
 *
 * Source: https://sqlite.org/withoutrowid.html, https://sqlite.org/stricttables.html.
 *
 * @visibility SqlSemantics
 */
enum TableOptionKind
{
    case WithoutRowid;
    case Strict;
}
