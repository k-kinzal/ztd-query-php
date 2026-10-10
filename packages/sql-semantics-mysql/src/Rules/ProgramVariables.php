<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules;

use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramRow;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\VariableDeclaration;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * Resolves a name of a stored program statement to a parameter, a local variable, or a column of the row of a trigger.
 *
 * Rule: MYSQL-PROGRAM-VARIABLE-LOOKUP-001. An unqualified name that a parameter
 * or local variable in scope has denotes that variable before any column of
 * the same name: the server reads a simple identifier of a stored program as
 * a variable when one of that name is declared around it. The innermost
 * declaration wins, and a name declared twice in one declaration keeps its
 * first position. The variables are found in the environment, while the
 * program is derived, and else among the rows of the program a running
 * statement is given (Settings::$program). A name at a position that names
 * the columns a statement writes (Environment::$written: the columns of
 * INSERT and LOAD DATA, the targets of SET in UPDATE and INSERT) is always a
 * column. A name qualified with NEW or OLD in a running trigger is a column
 * of that row, or ER_BAD_FIELD_ERROR when the row has none of the name.
 * Terminates: every search is over a finite list.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/local-variable-scope.html,
 * https://dev.mysql.com/doc/refman/8.4/en/trigger-syntax.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ProgramVariables
{
    /**
     * Finds the variable or trigger row column a name denotes, or null when it denotes neither.
     */
    public function find(Environment $environment, Name $column, ?QualifiedName $qualifier = null): ResolvedColumn|MissingColumn|null
    {
        if ($environment->written) {
            return null;
        }
        if ($qualifier !== null) {
            return $this->field($environment, $column, $qualifier);
        }
        for ($scope = $environment; $scope !== null; $scope = $scope->outer) {
            if ($scope->written) {
                return null;
            }
            for ($index = count($scope->relations) - 1; $index >= 0; $index--) {
                $relation = $scope->relations[$index];
                if (!$relation->relation instanceof VariableDeclaration && !$relation->relation instanceof ParameterList) {
                    continue;
                }
                foreach ($relation->shape->slots as $position => $slot) {
                    if ($slot->name !== null && !in_array($position, $relation->hidden, true) && strcasecmp($slot->name->value, $column->value) === 0) {
                        return new ResolvedColumn($relation->relation, $slot);
                    }
                }
            }
        }
        $found = Settings::of($environment->context)->variable($column->value);

        return $found === null ? null : $this->resolved($found[0], $found[1]);
    }

    /**
     * Finds a column of the NEW or OLD row of a running trigger, or null when the qualifier names no such row.
     */
    public function field(Environment $environment, Name $column, QualifiedName $qualifier): ResolvedColumn|MissingColumn|null
    {
        $row = $qualifier->schema === null ? Settings::of($environment->context)->row($qualifier->name->value) : null;
        if ($row === null) {
            return null;
        }
        $position = $row->position($column);

        return $position === null ? new MissingColumn($column, $qualifier) : $this->resolved($row, $position);
    }

    /**
     * Answers the resolution of the name at a position of a row: a value of its type that can be NULL.
     */
    public function resolved(ProgramRow $row, int $position): ResolvedColumn
    {
        return new ResolvedColumn($row->relation, new OutputSlot($row->names[$position], new Known($row->domains[$position]), Nullability::Nullable));
    }
}
