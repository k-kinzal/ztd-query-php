<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Collate;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\DoubleQuotedWord;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Expression\TruthWord;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\OutputOrdinal;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OpenStar;

/**
 * Derives ORDER BY and GROUP BY terms in the environment SQLite resolves each of them in.
 *
 * Rule: SQLITE-SORT-SCOPE-001. An integer constant denotes the result column
 * at that position (SQLITE-OUTPUT-ORDINAL-001). In ORDER BY, a term that is
 * one unqualified word, possibly in parentheses or under COLLATE, and equals
 * a result column alias denotes that result column before any input column
 * of the same name. Every other term is an expression over the input
 * columns, in which a result column alias is used when no input column has
 * the name. Terminates: one pass over the terms.
 * Source: https://sqlite.org/lang_select.html#the_order_by_clause. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class SortScopes
{
    /**
     * Derives the terms.
     *
     * @param list<Scalar> $terms The term expressions in written order
     * @param Environment $environment The environment of an ordinary term: input relations and aliases
     * @param list<Field|OpenStar> $projection The result columns of the query
     * @param bool $aliasFirst Whether a bare alias wins over an input column, as in ORDER BY
     */
    public function derive(array $terms, Derivation $derivation, Environment $environment, array $projection, bool $aliasFirst): void
    {
        $known = [];
        $open = false;
        foreach ($projection as $item) {
            if (!$item instanceof Field) {
                $open = true;
                break;
            }
            $known[] = $item;
        }
        foreach ($terms as $term) {
            $word = $aliasFirst ? $this->word($term) : null;
            if ($term instanceof OutputOrdinal) {
                $derivation->scalar($term, new Environment($derivation->context, $environment->outer, $open ? $environment->relations : [], [], $known));
            } elseif ($word !== null && $environment->aliased($word) !== []) {
                $derivation->scalar($term, new Environment($derivation->context, $environment->outer, [], [], $environment->aliased($word)));
            } else {
                $derivation->scalar($term, $environment);
            }
        }
    }

    /**
     * Answers the unqualified word a term consists of, or null when the term is more than one word.
     */
    public function word(Scalar $term): ?Name
    {
        while ($term instanceof Grouped || $term instanceof Collate) {
            $term = $term->operand;
        }
        if ($term instanceof ColumnUse) {
            return $term->qualifier === null ? $term->name : null;
        }
        if ($term instanceof DoubleQuotedWord) {
            return $term->word;
        }

        return $term instanceof TruthWord ? new Name($term->value ? 'true' : 'false') : null;
    }
}
