<?php

declare(strict_types=1);

namespace SqlSemantics\Statement;

/**
 * The SQL language a statement is written in, which decides whether the statement is valid SQL.
 *
 * A statement is valid in its language when the SQL it writes is read back
 * by that language as the same command with the same comments. That one
 * condition covers everything a typed value alone cannot know: whether a name
 * is a reserved word of the release, whether an operand binds strongly enough
 * without parentheses, whether a comment ends where it should, and whether
 * the release has the form at all. `SqlSemantics\Core\Language` is the
 * language of an analyzed or composed statement.
 *
 * @visibility public
 * @example The language of an analyzed statement
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $semantics->analyze('SELECT 1')->syntax === $semantics->language() // => true
 */
interface Syntax
{
    /**
     * Requires the SQL the statement writes to be read back as the same command and comments.
     *
     * @throws StatementException When the statement is not valid SQL of this language
     */
    public function verify(Statement $statement): void;
}
