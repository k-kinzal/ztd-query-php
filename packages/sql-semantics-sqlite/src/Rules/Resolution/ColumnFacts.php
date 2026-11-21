<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Resolution;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Resolution\Environment;
use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\Resolution;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Dependent;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Invalid;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\DoubleQuotedWord;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Expression\TruthWord;

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
