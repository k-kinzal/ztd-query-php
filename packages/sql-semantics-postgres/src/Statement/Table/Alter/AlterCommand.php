<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter;

use SqlSemantics\Platform\PostgreSql\Statement\Clause;

/**
 * One action of ALTER TABLE, ALTER INDEX, ALTER SEQUENCE, ALTER VIEW, ALTER MATERIALIZED VIEW or ALTER FOREIGN TABLE.
 *
 * Mirrors PostgreSQL's `AlterTableCmd` and `PartitionCmd`; each
 * implementation is one group of `AlterTableType` values with the same
 * operands. An action is derived where the altered relation is the only
 * visible relation; it changes no declaration of the context.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility SqlSemantics
 */
interface AlterCommand extends Clause
{
}
