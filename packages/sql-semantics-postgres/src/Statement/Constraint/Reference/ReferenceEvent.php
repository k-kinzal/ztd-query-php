<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference;

/**
 * The change of a referenced row a referential action answers.
 *
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html#SQL-CREATETABLE-PARMS-REFERENCES.
 *
 * @visibility SqlSemantics
 */
enum ReferenceEvent: string
{
    case Update = 'UPDATE';
    case Delete = 'DELETE';
}
