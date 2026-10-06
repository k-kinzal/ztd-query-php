<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Routine;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\ColumnResolver;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerTime;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\NameAssignment;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\MissingColumn;

/**
 * Checks the assignments of SET to a column of the NEW or OLD row in a trigger body.
 *
 * Rule: MYSQL-TRIGGER-ASSIGNMENT-001. In a trigger, a SET item without a
 * scope keyword whose name is qualified by NEW or OLD, in any letter case,
 * assigns to a column of the row of the trigger table. The OLD row cannot
 * be changed (ER_TRG_CANT_CHANGE_ROW); a DELETE trigger has no NEW row
 * (ER_TRG_NO_SUCH_ROW_IN_TRG); an AFTER trigger cannot change the NEW row
 * (ER_TRG_CANT_CHANGE_ROW). Otherwise the name is resolved as a column of
 * the NEW row, and a column the table does not have is reported
 * (ER_BAD_FIELD_ERROR); a table that is not completely known reports
 * nothing. The value is derived by the SET statement itself at the position
 * of the body, where NEW and OLD are visible. Terminates: one pass over the
 * items. Source: https://dev.mysql.com/doc/refman/8.4/en/trigger-syntax.html
 * ("In a BEFORE trigger, you can also change its value with SET NEW.col_name
 * = value"; "OLD columns are read only").
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TriggerAssignments
{
    /**
     * Checks the items of a SET statement of a trigger body that assign to the NEW or OLD row.
     */
    public function check(SetVariables $set, TriggerTime $time, TriggerEvent $event, Derivation $derivation, ProgramScope $scope): void
    {
        foreach ($set->items as $item) {
            if (!$item instanceof NameAssignment || $item->scope !== null || $item->qualifier === null) {
                continue;
            }
            $row = strtoupper($item->qualifier->value);
            $problem = match (true) {
                $row === 'OLD' => new ProgramProblem(ProgramRule::OldRowUpdate),
                $row !== 'NEW' => null,
                $event === TriggerEvent::Delete => new ProgramProblem(ProgramRule::NoNewRow),
                $time === TriggerTime::After => new ProgramProblem(ProgramRule::AfterRowUpdate),
                default => null,
            };
            if ($problem !== null) {
                $derivation->report($problem);
            } elseif ($row === 'NEW') {
                $resolution = (new ColumnResolver())->find($scope->environment, $item->name, new QualifiedName($item->qualifier));
                if ($resolution instanceof MissingColumn) {
                    $derivation->report($resolution);
                }
            }
        }
    }
}
