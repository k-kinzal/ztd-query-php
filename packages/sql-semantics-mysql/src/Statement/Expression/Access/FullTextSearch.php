<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Access;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Type\Floating;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\FloatingKind;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A full-text search: `MATCH (col, …) AGAINST (expr [mode])` (`Item_func_match`).
 *
 * The column list may be written with or without parentheses, and a
 * natural language search may state IN NATURAL LANGUAGE MODE; both forms
 * are kept, since they are part of the text MySQL names an unaliased select
 * list expression after. The search string is a bit_expr; before IN BOOLEAN
 * MODE it may not end in a form that would take the IN.
 *
 * Rule: MYSQL-FULLTEXT-001. Facts: the relevance, a DOUBLE; it is treated
 * as possibly NULL. Every column is resolved in the environment.
 * Terminates: the columns and the search string are strict parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/fulltext-search.html#function_match.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the searched columns
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT a FROM t WHERE MATCH a, b AGAINST ('x' WITH QUERY EXPANSION)");
 *     [count($query->statement->where->columns), $query->toString()] // => [2, "SELECT a FROM t WHERE MATCH a, b AGAINST ('x' WITH QUERY EXPANSION)"]
 */
final class FullTextSearch implements Scalar
{
    use Snapshot;

    /**
     * @var non-empty-list<ColumnUse> The searched columns in order
     */
    public readonly array $columns;

    /**
     * @param list<ColumnUse> $columns The searched columns in order; at least one
     * @param Scalar $against The search string
     * @param FullTextMode $mode The search mode
     * @param OptionalWords $parentheses Whether the column list is written in parentheses
     * @param OptionalWords $stated Whether a natural language search writes IN NATURAL LANGUAGE MODE
     */
    public function __construct(array $columns, public readonly Scalar $against, public readonly FullTextMode $mode = FullTextMode::NaturalLanguage, public readonly OptionalWords $parentheses = OptionalWords::Written, public readonly OptionalWords $stated = OptionalWords::Omitted)
    {
        $this->columns = Check::listOf($columns, ColumnUse::class, 'MATCH searches at least one column.', 1);
        $precedence = new Precedence();
        Check::input($precedence->admits($against, Precedence::BIT_EXPR), 'The search string of AGAINST needs a grouping to keep its place.');
        Check::input($stated === OptionalWords::Omitted || $mode !== FullTextMode::Boolean, 'Only a natural language search states IN NATURAL LANGUAGE MODE.');
        Check::input(($mode !== FullTextMode::Boolean && $stated === OptionalWords::Omitted) || !$precedence->absorbs($against, Precedence::PREDICATE, Precedence::BIT_EXPR), 'A search string followed by IN ... MODE needs a grouping to keep its place.');
    }

    /**
     * Derives the columns and the search string; the relevance is a DOUBLE.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $operands = new Operands();
        foreach ($this->columns as $column) {
            $operands->single($derivation->scalar($column, $environment), $derivation);
        }
        $operands->single($derivation->scalar($this->against, $environment), $derivation);

        return new ScalarFact(new Known(new Floating(FloatingKind::Double)), Nullability::Nullable);
    }

    /**
     * Writes MATCH, the columns, AGAINST, the search string and the mode.
     */
    public function render(Output $out): void
    {
        $out->keyword('MATCH');
        if ($this->parentheses === OptionalWords::Written) {
            $out->symbol('(')->list($this->columns)->symbol(')');
        } else {
            $out->list($this->columns);
        }
        $out->keyword('AGAINST')->symbol('(')->node($this->against);
        if ($this->stated === OptionalWords::Written) {
            $out->keyword('IN', 'NATURAL', 'LANGUAGE', 'MODE');
        }
        match ($this->mode) {
            FullTextMode::NaturalLanguage => $out,
            FullTextMode::QueryExpansion => $out->keyword('WITH', 'QUERY', 'EXPANSION'),
            FullTextMode::Boolean => $out->keyword('IN', 'BOOLEAN', 'MODE'),
        };
        $out->symbol(')');
    }
}
