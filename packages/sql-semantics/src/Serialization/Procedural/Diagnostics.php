<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Procedural;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Condition\ConditionDiagnostic;
use SqlSemantics\Model\Configuration\Condition\StatementDiagnostic;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Procedural\GetConditionDiagnosticsStatement;
use SqlSemantics\Model\Statement\Procedural\GetDiagnosticsStatement;
use SqlSemantics\Schema\VariableScope;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Serialization\Scalar\ReferenceExpressions;

/**
 * Writes GET DIAGNOSTICS with an explicit area, the optional condition number and its targets.
 * @visibility SqlSemantics
 */
final class Diagnostics
{
    /**
     * Always spells the diagnostics area, which is equivalent to omitting CURRENT.
     */
    public static function write(GetDiagnosticsStatement|GetConditionDiagnosticsStatement $statement): Tree
    {
        $condition = $statement instanceof GetConditionDiagnosticsStatement ? [Build::keyword('CONDITION'), Expressions::write($statement->condition)] : [];
        $items = Build::separated(array_map(static fn (StatementDiagnostic|ConditionDiagnostic $item): Tree => new Tree('diagnostic-item', [ReferenceExpressions::variable($item->variable, VariableScope::User, Dialect::MySql), Build::keyword('= ' . $item->item->value)]), $statement->items));
        return new Tree('get-diagnostics', [Build::keyword('GET ' . $statement->area->value . ' DIAGNOSTICS'), ...$condition, $items]);
    }
}
