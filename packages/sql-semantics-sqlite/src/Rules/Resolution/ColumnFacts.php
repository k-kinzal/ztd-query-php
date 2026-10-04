<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Resolution;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\DoubleQuotedWord;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Expression\TruthWord;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Column\Resolution;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Nullability;

/**
 * Derives the facts of a name from the outcome of its lookup.
 *
 * Rule: SQLITE-COLUMN-FACT-001. A resolved name has the type and NULL fact
 * of the slot it denotes; a result column alias has those of the result
 * column; a conditional name depends on the missing inputs; a missing or
 * ambiguous name is invalid. Source: https://sqlite.org/lang_expr.html#column_names.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ColumnFacts
{
    /**
     * Answers the facts of a lookup outcome.
     */
    public function of(Resolution $resolution): ScalarFact
    {
        if ($resolution instanceof ResolvedColumn) {
            return new ScalarFact($resolution->slot->type, $resolution->slot->nullability, $resolution);
        }
        if ($resolution instanceof AliasTarget) {
            return new ScalarFact($resolution->field->type, $resolution->field->nullability, $resolution);
        }
        if ($resolution instanceof ConditionalColumn) {
            return new ScalarFact(new Dependent($resolution->missing), Nullability::Dependent, $resolution);
        }
        Check::invariant($resolution instanceof Diagnostic, 'A column lookup resolves, depends on missing inputs, or reports a problem.');

        return new ScalarFact(new Invalid($resolution), Nullability::Dependent, $resolution);
    }

    /**
     * Answers the name resolution of an expression that is a name use, possibly in parentheses, or null when it is none.
     *
     * SQLite keeps no node for a grouping: a column in parentheses is that
     * column. The fact of a grouping carries no resolution of its own, so
     * the outcome is looked up for the name use the groupings enclose. A
     * double-quoted word or truth word that names no column is a literal and
     * has no resolution.
     */
    public function denoted(Scalar $expression, Environment $environment): ?Resolution
    {
        while ($expression instanceof Grouped) {
            $expression = $expression->operand;
        }
        if ($expression instanceof ColumnUse) {
            return (new ColumnResolver())->find($environment, $expression->name, $expression->qualifier);
        }
        if (!$expression instanceof DoubleQuotedWord && !$expression instanceof TruthWord) {
            return null;
        }
        $resolution = (new ColumnResolver())->find($environment, $expression instanceof DoubleQuotedWord ? $expression->word : new Name($expression->value ? 'true' : 'false'));

        return $resolution instanceof MissingColumn ? null : $resolution;
    }
}
