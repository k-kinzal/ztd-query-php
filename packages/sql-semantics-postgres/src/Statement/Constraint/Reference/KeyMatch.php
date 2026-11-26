<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference;

/**
 * How a foreign key matches referencing values that contain NULL.
 *
 * Mirrors `fk_matchtype` (`FKCONSTR_MATCH_FULL`, `PARTIAL`, `SIMPLE`). SIMPLE
 * is the default and is kept when written. PARTIAL is not implemented by the
 * server.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html#SQL-CREATETABLE-PARMS-REFERENCES.
 *
 * @visibility SqlSemantics
 */
enum KeyMatch: string
{
    case Full = 'FULL';
    case Partial = 'PARTIAL';
    case Simple = 'SIMPLE';
}
