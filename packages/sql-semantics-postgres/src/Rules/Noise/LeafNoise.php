<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Noise;

/**
 * The token positions of the leaf family: the statement list, names, constants and shared options that carry no meaning.
 *
 * A position is listed only when the token there has no effect on what the
 * statement requests in that production, and each entry states the reason and
 * cites the manual. A significant token never belongs here: when rendering
 * cannot reproduce it, the model lacks a distinction.
 *
 * @visibility SqlSemantics
 */
final class LeafNoise
{
    /**
     * Answers the noise positions by production signature.
     *
     * @return array<string, list<int>>
     */
    public static function positions(): array
    {
        return [
            // The semicolon separates statements; each statement is an item of the script. https://www.postgresql.org/docs/17/sql-syntax-lexical.html#SQL-SYNTAX-LEXICAL
            'stmtmulti: stmtmulti ; toplevel_stmt' => [1],
            // Unary plus before a number is no operation; the grammar action drops it. https://www.postgresql.org/docs/17/functions-math.html#FUNCTIONS-MATH-OP-TABLE
            'NumericOnly: + FCONST' => [0],
            'SignedIconst: + Iconst' => [0],
            // "If no operation is explicitly specified, ADD is assumed." https://www.postgresql.org/docs/17/sql-alterforeigndatawrapper.html
            'alter_generic_option_elem: ADD_P generic_option_elem' => [0],
            // [ WITH ] before role, database, extension, sequence and copy options is optional. https://www.postgresql.org/docs/17/sql-createrole.html https://www.postgresql.org/docs/17/sql-createdatabase.html https://www.postgresql.org/docs/17/sql-createsequence.html https://www.postgresql.org/docs/17/sql-copy.html
            'opt_with: WITH' => [0],
            'opt_with: WITH_LA' => [0],
            // [ AS ] before a domain type, a transition relation name and a COPY option value is optional. https://www.postgresql.org/docs/17/sql-createdomain.html https://www.postgresql.org/docs/17/sql-createtrigger.html https://www.postgresql.org/docs/17/sql-copy.html
            'opt_as: AS' => [0],
            // [ TABLE ] in TRUNCATE, LOCK and SELECT INTO is optional. https://www.postgresql.org/docs/17/sql-truncate.html https://www.postgresql.org/docs/17/sql-lock.html https://www.postgresql.org/docs/17/sql-selectinto.html
            'opt_table: TABLE' => [0],
            // [ COLUMN ] in ALTER TABLE actions and RENAME is optional. https://www.postgresql.org/docs/17/sql-altertable.html
            'opt_column: COLUMN' => [0],
            // [ PROCEDURAL ] before LANGUAGE is a noise word. https://www.postgresql.org/docs/17/sql-createlanguage.html
            'opt_procedural: PROCEDURAL' => [0],
            // [ SET DATA ] before TYPE is optional. https://www.postgresql.org/docs/17/sql-altertable.html
            'opt_set_data: SET DATA_P' => [0, 1],
        ];
    }
}
