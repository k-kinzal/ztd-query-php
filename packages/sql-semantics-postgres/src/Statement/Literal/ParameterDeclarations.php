<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Literal;

use SqlSemantics\Statement\Relation;

/**
 * A list that declares the types of the positional parameters of the statements it encloses.
 *
 * The parameter list of a routine with an SQL-standard body and the type
 * list of PREPARE give `$1`, `$2` and so on their types. The list is a
 * relation whose row holds one slot per parameter in order: `$n` denotes
 * slot n of the nearest such list visible from its position.
 * Source: https://www.postgresql.org/docs/17/xfunc-sql.html#XFUNC-SQL-FUNCTION-ARGUMENTS,
 * https://www.postgresql.org/docs/17/sql-prepare.html.
 *
 * @visibility public
 * @example Typing a parameter of a prepared statement from its declared type
 *     $prepare = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('PREPARE p (integer) AS SELECT $1');
 *     $prepare->statement->parameters?->infersUndeclared() // => true
 */
interface ParameterDeclarations extends Relation
{
    /**
     * Tells whether a parameter beyond the declared ones is inferred from its use, as in PREPARE, instead of being an error, as in a routine body.
     */
    public function infersUndeclared(): bool;
}
