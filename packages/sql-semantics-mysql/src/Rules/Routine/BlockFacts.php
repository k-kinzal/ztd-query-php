<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Routine;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Condition;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ConditionDeclaration;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\CursorDeclaration;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\HandlerDeclaration;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\VariableDeclaration;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Derives a BEGIN ... END block: its label, its declarations in order, and its statements.
 *
 * Rule: MYSQL-PROGRAM-BLOCK-001. The block opens a scope inside the scope
 * of its position. Each variable declaration adds its names to the scope
 * of everything after it; a name declared twice in one block is reported
 * (ER_SP_DUP_VAR) and keeps its first meaning, while a name of an outer
 * block or a parameter is hidden. A condition or cursor declared twice in
 * one block is reported (ER_SP_DUP_COND, ER_SP_DUP_CURS), and so is a
 * condition value a handler of the block already handles, by the same
 * handler or an earlier one (ER_SP_DUP_HANDLER). Variables and
 * conditions are declared before cursors and handlers
 * (ER_SP_VARCOND_AFTER_CURSHNDLR), cursors before handlers
 * (ER_SP_CURSOR_AFTER_HANDLER). The query of a cursor is derived as a nested
 * query; the conditions of a handler by MYSQL-PROGRAM-CONDITIONS-001; the
 * statement of a handler in the scope of the declarations before it without
 * the labels outside the handler. Terminates: one pass over the
 * declarations; nested statements are strict parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/begin-end.html,
 * https://dev.mysql.com/doc/refman/8.4/en/declare.html,
 * https://dev.mysql.com/doc/refman/8.4/en/local-variable-scope.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class BlockFacts
{
    /**
     * Derives the block in the scope of its position.
     */
    public function derive(Block $block, Derivation $derivation, ProgramScope $scope): void
    {
        $outer = (new LabelFacts())->enter($block->label, $block->endLabel, false, $derivation, $scope);
        $inside = $outer->declared(new Environment($derivation->context, $outer->environment), $outer->conditions, $outer->cursors);
        $stage = 0;
        $names = [];
        $conditions = [];
        $cursors = [];
        $handled = [];
        foreach ($block->declarations as $declaration) {
            if ($declaration instanceof VariableDeclaration) {
                $this->ordered($stage, 0, $derivation);
                $inside = $this->variables($declaration, $names, $derivation, $inside);
                array_push($names, ...$declaration->names);
            } elseif ($declaration instanceof ConditionDeclaration) {
                $this->ordered($stage, 0, $derivation);
                $this->distinct($conditions, $declaration->name, ProgramRule::DuplicateCondition, $derivation, $inside);
                (new ConditionFacts())->value($declaration->value, $derivation, $inside);
                $conditions[] = $declaration->name;
                $inside = $inside->declared($inside->environment, [...$inside->conditions, $declaration], $inside->cursors);
            } elseif ($declaration instanceof CursorDeclaration) {
                $this->ordered($stage, 1, $derivation);
                $stage = max($stage, 1);
                $this->distinct($cursors, $declaration->name, ProgramRule::DuplicateCursor, $derivation, $inside);
                $derivation->query($declaration->query, $inside->environment);
                $cursors[] = $declaration->name;
                $inside = $inside->declared($inside->environment, $inside->conditions, [...$inside->cursors, $declaration->name]);
            } elseif ($declaration instanceof HandlerDeclaration) {
                $stage = 2;
                foreach ($declaration->conditions as $condition) {
                    $handled = $this->handled($handled, $condition, $derivation, $inside);
                }
                (new BodyFacts())->statement($declaration->statement, $derivation, $inside->handler());
            }
        }
        (new BodyFacts())->statements($block->statements, $derivation, $inside);
    }

    /**
     * Reports a declaration written after a kind of declaration it must precede.
     *
     * @param int $stage The latest kind declared so far: 0 variables and conditions, 1 cursors, 2 handlers
     * @param int $kind The kind of this declaration: 0 a variable or condition, 1 a cursor
     */
    public function ordered(int $stage, int $kind, Derivation $derivation): void
    {
        if ($kind === 0 && $stage > 0) {
            $derivation->report(new ProgramProblem(ProgramRule::DeclarationAfterCursorOrHandler));
        }
        if ($kind === 1 && $stage > 1) {
            $derivation->report(new ProgramProblem(ProgramRule::CursorAfterHandler));
        }
    }

    /**
     * Reports a name the block already declared for the same kind of object.
     *
     * @param list<Name> $declared The names of that kind the block declared before
     */
    public function distinct(array $declared, Name $name, ProgramRule $rule, Derivation $derivation, ProgramScope $scope): void
    {
        if ($scope->holds($declared, $name)) {
            $derivation->report(new ProgramProblem($rule, $name->value));
        }
    }

    /**
     * Checks a condition value of a handler and answers the values the handlers of the block handle with it.
     *
     * @param list<Condition> $handled The values the handlers of the block handle so far
     *
     * @return list<Condition>
     */
    public function handled(array $handled, Condition $condition, Derivation $derivation, ProgramScope $scope): array
    {
        $facts = new ConditionFacts();
        $facts->value($condition, $derivation, $scope);
        $meaning = $facts->meaning($condition, $scope);
        if ($meaning === null) {
            return $handled;
        }
        foreach ($handled as $earlier) {
            if ($facts->same($earlier, $meaning)) {
                $derivation->report(new ProgramProblem(ProgramRule::DuplicateHandler));

                return $handled;
            }
        }

        return [...$handled, $meaning];
    }

    /**
     * Derives a variable declaration and answers the scope in which its names are visible.
     *
     * @param list<Name> $declared The variable names the block declared before
     */
    public function variables(VariableDeclaration $declaration, array $declared, Derivation $derivation, ProgramScope $scope): ProgramScope
    {
        $fact = $derivation->relation($declaration, $scope->environment);
        $hidden = [];
        foreach ($declaration->names as $position => $name) {
            if ($scope->holds($declared, $name)) {
                $derivation->report(new ProgramProblem(ProgramRule::DuplicateVariable, $name->value));
                $hidden[] = $position;
            }
            $declared[] = $name;
        }
        $environment = new Environment($derivation->context, $scope->environment->outer, [...$scope->environment->relations, new VisibleRelation($declaration, $fact->shape, null, null, $hidden)]);

        return $scope->declared($environment, $scope->conditions, $scope->cursors);
    }
}
