<?php

declare(strict_types=1);

namespace SqlSemantics\Statement;

use RuntimeException;

/**
 * A statement that is not valid SQL of its language.
 *
 * The values of a statement can be combined in ways no release reads: a
 * reserved word used as a name, an operand that binds too weakly to stand
 * without parentheses, a comment that swallows the SQL after it, or a form
 * another release has. Whether the SQL a statement writes is read back as
 * itself depends on the release and the values chosen at run time, so this
 * is a runtime failure the code building or rewriting a statement has to
 * expect.
 *
 * @visibility public
 * @example Reporting a statement its language reads as other SQL
 *     $error = new \SqlSemantics\Statement\StatementException('The statement is read back as other SQL');
 *     $error->getMessage() // => 'The statement is read back as other SQL'
 */
final class StatementException extends RuntimeException
{
}
