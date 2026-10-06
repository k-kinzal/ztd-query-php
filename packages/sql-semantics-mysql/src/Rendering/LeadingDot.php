<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rendering;

use SqlSemantics\Rendering\Output;

/**
 * Writes the leading dot of a 5.6 or 5.7 name such as `.t` or `.t.a`, apart from the word before it.
 *
 * Rule: MYSQL-LEADING-DOT-001. The MySQL lexer reads a word directly
 * followed by a dot and an identifier as a qualifier, not as a keyword
 * (`MY_LEX_IDENT_SEP` in sql/sql_lex.cc), so `FROM.t` is not `FROM .t`. The
 * output writer joins a dot to the piece before it, so the leading dot is
 * written as a piece of its own that the writer separates from a preceding
 * word, and the name after it is glued to it. The written token is the dot
 * either way. Source: sql/sql_lex.cc and sql/sql_yacc.yy (`table_ident:
 * '.' ident`, `simple_ident_q: '.' ident '.' ident`) of MySQL 5.7.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class LeadingDot
{
    /**
     * Writes the dot so that the next piece is joined to it.
     */
    public function write(Output $out): void
    {
        $out->spelled('.')->glue();
    }
}
