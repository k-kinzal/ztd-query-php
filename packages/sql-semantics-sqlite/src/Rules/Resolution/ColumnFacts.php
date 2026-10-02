<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Resolution;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Statement\Reference\Column\Resolution;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
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
}
