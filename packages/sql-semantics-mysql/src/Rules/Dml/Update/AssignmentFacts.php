<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Dml\Update;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Dml\WriteScope;
use SqlSemantics\Platform\MySql\Rules\Expression\Preparation\DeferredArguments;
use SqlSemantics\Platform\MySql\Statement\Dml\Assignment;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredColumnAssignment;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Shape\Field;

/**
 * Binds UPDATE destinations before deriving the ordered assignment values.
 *
 * Every destination belongs to the write namespace, while values can refer to
 * program variables. A retained row assignment preserves its own dependencies
 * and reports its width before a later assignment's value is derived.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class AssignmentFacts
{
    /**
     * @param list<Assignment|DeferredColumnAssignment> $assignments The complete SET list
     * @return list<Field> The bound destinations in written order
     */
    public function derive(array $assignments, Derivation $derivation, Environment $environment): array
    {
        $scope = new WriteScope();
        $columns = $scope->written($environment);
        $fields = [];
        foreach ($assignments as $position => $assignment) {
            $fields[] = $scope->field($position, $assignment->column, $derivation->scalar($assignment->column, $columns));
        }
        foreach ($assignments as $position => $assignment) {
            if ($assignment instanceof DeferredColumnAssignment) {
                $derivation->scalar($assignment->value, $environment);
                $derivation->report((new DeferredArguments())->problem([$assignment->value]));
            } else {
                $scope->value($assignment->value, $fields[$position], $derivation, $environment);
            }
        }
        return $fields;
    }
}
