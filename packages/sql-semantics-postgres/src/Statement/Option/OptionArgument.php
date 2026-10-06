<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Option;

use SqlSemantics\Platform\PostgreSql\Statement\Clause;

/**
 * A value given to a named option or to a configuration parameter.
 *
 * PostgreSQL's option syntax accepts a type name, a word, a reserved keyword,
 * an operator, a signed number or a string constant, and passes each to the
 * command as written. A word and a string constant with the same text are the
 * same value to most commands; they are kept apart because the grammar reads
 * them as different operands.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html#SQL-CREATETABLE-STORAGE-PARAMETERS,
 * https://www.postgresql.org/docs/17/sql-set.html.
 *
 * @visibility public
 * @example Telling that a signed number is an option value
 *     $number = new \SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber(true, new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('1'));
 *     $number instanceof \SqlSemantics\Platform\PostgreSql\Statement\Option\OptionArgument // => true
 */
interface OptionArgument extends Clause
{
}
