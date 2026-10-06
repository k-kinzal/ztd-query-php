<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Definition;

use SqlSemantics\Platform\Sqlite\Statement\Expression\Collate;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\DoubleQuotedWord;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Schema\LiteralColumn;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;

/**
 * Reads the column a term of a PRIMARY KEY or UNIQUE table constraint names.
 *
 * Rule: SQLITE-KEY-TERM-001. The grammar writes key terms as expressions.
 * SQLite looks through parentheses and COLLATE and takes the term as a key
 * column when what remains is a plain name, written as an identifier, as a
 * double-quoted word or as a string literal (LiteralColumn: a string under
 * any number of COLLATE clauses in a key constraint, under at most one in an
 * index term, is read as an identifier; the structure must write it as a
 * LiteralColumn, so a plain string there is refused at construction). A
 * qualified column reference is still a column for the index of the
 * constraint, but it does not mark the column as part of the primary key;
 * anything else is "expressions prohibited in PRIMARY KEY and UNIQUE
 * constraints". Terminates: each step removes one wrapper of a finite
 * expression. Source: https://sqlite.org/lang_createtable.html#the_primary_key
 * (and `sqlite3AddPrimaryKey()`, `sqlite3StringToId()` and
 * `sqlite3CreateIndex()` in build.c of the release). Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class KeyTerms
{
    /**
     * Answers the expression of a term without its parentheses and collations.
     */
    public function core(Scalar $term): Scalar
    {
        while ($term instanceof Grouped || $term instanceof Collate) {
            $term = $term->operand;
        }

        return $term;
    }

    /**
     * Answers the expression of a term without its parentheses and at most the given number of collations; null admits any number.
     */
    public function beneath(Scalar $term, ?int $collations): Scalar
    {
        while ($term instanceof Grouped || ($term instanceof Collate && ($collations === null || $collations > 0))) {
            if ($term instanceof Collate) {
                $collations = $collations === null ? null : $collations - 1;
            }
            $term = $term->operand;
        }

        return $term;
    }

    /**
     * Tells whether a term is a string literal SQLite would read as a column name, which the structure must write as a LiteralColumn.
     */
    public function string(Scalar $term, ?int $collations): bool
    {
        return $this->beneath($term, $collations) instanceof TextLiteral;
    }

    /**
     * Answers the name of the key column a term denotes, or null when the term is not a plain name.
     */
    public function column(Scalar $term): ?Name
    {
        $core = $this->core($term);
        if ($core instanceof ColumnUse) {
            return $core->qualifier === null ? $core->name : null;
        }
        if ($core instanceof DoubleQuotedWord) {
            return $core->word;
        }

        return $core instanceof LiteralColumn ? $core->name() : null;
    }

    /**
     * Tells whether a term is a column reference of any form, which a key constraint accepts.
     */
    public function reference(Scalar $term): bool
    {
        $core = $this->core($term);

        return $core instanceof ColumnUse || $core instanceof DoubleQuotedWord || $core instanceof LiteralColumn;
    }
}
