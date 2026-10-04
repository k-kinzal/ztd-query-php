<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Constraint;

/**
 * The kinds of constraint PostgreSQL distinguishes.
 *
 * Mirrors the `ConstrType` values of PostgreSQL's `Constraint` node other
 * than the attribute markers, which are `ConstraintAttribute` values here.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html.
 *
 * @visibility public
 * @example Reading the kind of a table constraint
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TABLE t (a int, PRIMARY KEY (a))');
 *     $create->statement->definition->elements[1]->kind() // => \SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintKind::PrimaryKey
 */
enum ConstraintKind
{
    case Null;
    case NotNull;
    case Default;
    case Identity;
    case Generated;
    case Check;
    case PrimaryKey;
    case Unique;
    case Exclusion;
    case ForeignKey;
}
