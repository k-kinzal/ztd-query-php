<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Routine\Declaration;

use SqlSemantics\Platform\MySql\Rules\Typing\Ordering\GeneralWeights;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Platform\MySql\Statement\Routine\Program as P;

/**
 * Checks declaration order and local name uniqueness within one lexical block.
 *
 * Variable, condition and cursor names occupy independent namespaces. An outer
 * declaration may be shadowed, but a repeated name in the same namespace and
 * block cannot be repaired by execution state. Observed through SQL on MySQL
 * 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class DeclarationNames
{
    /**
     * Checks the next declaration before publishing its names to the scope.
     *
     * @param array<string, array<string, true>> $names Names already declared by kind in this block
     * @param int $stage The latest declaration category: variables/conditions, cursors, handlers
     */
    public function add(P\Declaration $declaration, array &$names, int &$stage): ?ProgramProblem
    {
        $kind = $declaration instanceof P\HandlerDeclaration ? 2 : ($declaration instanceof P\Cursor\CursorDeclaration ? 1 : 0);
        if ($kind === 0 && $stage > 0) {
            return new ProgramProblem(ProgramRule::DeclarationAfterCursorOrHandler);
        }
        if ($kind === 1 && $stage > 1) {
            return new ProgramProblem(ProgramRule::CursorAfterHandler);
        }
        $stage = max($stage, $kind);
        $rule = match (true) {
            $declaration instanceof P\VariableDeclaration => ProgramRule::DuplicateVariable,
            $declaration instanceof P\ConditionDeclaration => ProgramRule::DuplicateCondition,
            $declaration instanceof P\Cursor\CursorDeclaration => ProgramRule::DuplicateCursor,
            default => null,
        };
        if ($rule === null) {
            return null;
        }
        $declared = $declaration instanceof P\VariableDeclaration ? $declaration->names : [$declaration->name];
        return $this->names($declared, $names, $rule);
    }

    /**
     * Publishes completed names before their declaration type or default is read.
     *
     * @param list<\SqlSemantics\Statement\Identifier\Name> $declared Names in input order
     * @param array<string, array<string, true>> $names Existing names in this lexical block
     */
    public function names(array $declared, array &$names, ProgramRule $rule): ?ProgramProblem
    {
        foreach ($declared as $name) {
            $key = (new GeneralWeights())->key($name->value);
            if (isset($names[$rule->name][$key])) {
                return new ProgramProblem($rule, $name->value);
            }
            $names[$rule->name][$key] = true;
        }
        return null;
    }
}
