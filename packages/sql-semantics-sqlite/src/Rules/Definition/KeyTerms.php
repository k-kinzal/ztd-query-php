<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Definition;

use SqlSemantics\Platform\Sqlite\Statement\Expression\Collate;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\DoubleQuotedWord;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TextLiteral;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;

/**
 * Reads the column a term of a PRIMARY KEY or UNIQUE table constraint names.
 *
 * Rule: SQLITE-KEY-TERM-001. The grammar writes key terms as expressions.
 * SQLite looks through parentheses and COLLATE, reads a string literal as an
 * identifier, and takes the term as a key column when what remains is a
 * plain name. A qualified column reference is still a column for the index
 * of the constraint, but it does not mark the column as part of the primary
 * key; anything else is "expressions prohibited in PRIMARY KEY and UNIQUE
 * constraints". Terminates: each step removes one wrapper of a finite
 * expression. Source: https://sqlite.org/lang_createtable.html#the_primary_key
 * (and `sqlite3AddPrimaryKey()` in build.c of the release). Status: Implemented.
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

        return $core instanceof TextLiteral ? new Name($core->value) : null;
    }

    /**
     * Tells whether a term is a column reference of any form, which a key constraint accepts.
     */
    public function reference(Scalar $term): bool
    {
        $core = $this->core($term);

        return $core instanceof ColumnUse || $core instanceof DoubleQuotedWord || $core instanceof TextLiteral;
    }
}
