<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Routine\Declaration;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramRestrictions;
use SqlSemantics\Platform\MySql\Rules\Routine\ReturnSearch;
use SqlSemantics\Platform\MySql\Statement\Routine\ExternalBody;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement;
use SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;

/**
 * Checks the declaration kind against every retained program instruction.
 *
 * Traversal includes unreachable branches and handlers. Body-wide restrictions
 * have native priority independent of instruction order: dynamic SQL, result
 * sets, autocommit changes, transaction boundaries, RESET, then FLUSH. Immediate restrictions on
 * instructions of any stored program precede those body-wide checks. These
 * rules describe the language structure, not execution eligibility or state.
 * Observed through SQL on MySQL 5.6, 5.7, 8.0, 8.4 and 9.1.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class BodyStructure
{
    /**
     * Refuses an instruction arrangement with no interpretation for its declaration kind.
     */
    public function check(Node $body, ProgramKind $kind, ?QualifiedName $name = null, ?\SqlSemantics\Platform\MySql\Statement\Routine\ParameterList $parameters = null): void
    {
        $recursive = (new \SqlSemantics\Platform\MySql\Rules\Query\Recursion\Constraints())->position($body);
        Check::input($recursive === null, $recursive?->message() ?? 'Recursive references occupy their own query block.');
        $locals = array_map(static fn (\SqlSemantics\Platform\MySql\Statement\Routine\Parameter $parameter): \SqlSemantics\Statement\Identifier\Name => $parameter->name, $parameters->parameters ?? []);
        Check::input((new LocalAssignments())->scoped($body, $locals) === null, (new \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ScopedLocalAssignment())->message());
        $problem = $this->problem($body, $kind, $name, $parameters);
        Check::input($problem === null, $problem?->message() ?? 'The body fits its declaration kind.');
        (new BodyBindings())->check($body, $kind);
        (new BodyArrays())->check($body);
    }

    /**
     * Answers the first immediate restriction or the highest-priority body-wide restriction.
     */
    public function problem(Node $body, ProgramKind $kind, ?QualifiedName $name = null, ?\SqlSemantics\Platform\MySql\Statement\Routine\ParameterList $parameters = null): ?ProgramProblem
    {
        if ($body instanceof ExternalBody) {
            return null;
        }
        $labels = (new BodyLabels())->problem($body);
        if ($labels !== null) {
            return $labels;
        }
        $pending = [$body];
        $found = ($kind === ProgramKind::Function || $kind === ProgramKind::Trigger) && (new BodyAssignments())->forbidden($body, $kind === ProgramKind::Trigger ? new \SqlSemantics\Platform\MySql\Statement\Routine\ParameterList([]) : $parameters)
            ? new ProgramProblem(ProgramRule::AutocommitInFunction) : null;
        $rules = new ProgramRestrictions();
        $search = new ReturnSearch();
        while ($pending !== []) {
            $current = array_pop($pending);
            $immediate = $current instanceof Statement ? $rules->anywhere($current) : null;
            if ($immediate !== null) {
                return $immediate;
            }
            if ($current instanceof ReturnStatement && $kind !== ProgramKind::Function) {
                return new ProgramProblem(ProgramRule::ReturnOutsideFunction);
            }
            $problem = $this->contained($current, $kind);
            if ($problem !== null && ($found === null || $this->priority($problem) < $this->priority($found))) {
                $found = $problem;
            }
            array_push($pending, ...array_reverse($search->nested($current)));
        }
        return $found ?? ($kind === ProgramKind::Function && !$search->found($body) ? new ProgramProblem(ProgramRule::MissingReturn, $name?->name->value ?? '') : null);
    }

    /**
     * Answers a body-wide function or trigger restriction for one instruction.
     */
    public function contained(Node $statement, ProgramKind $kind): ?ProgramProblem
    {
        if ($kind !== ProgramKind::Function && $kind !== ProgramKind::Trigger) {
            return null;
        }
        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredExplain || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection || ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredQuery && !(new ProgramRestrictions())->into($statement->query))) {
            return new ProgramProblem(ProgramRule::ResultSet, strtolower($kind->value));
        }
        if ((new TransactionStatements())->implicit($statement)) {
            return new ProgramProblem(ProgramRule::CommitInFunction);
        }
        return $statement instanceof Statement ? (new ProgramRestrictions())->function($statement, $kind) : null;
    }

    /**
     * Orders the restrictions checked after the complete body has been read.
     */
    public function priority(ProgramProblem $problem): int
    {
        return match ($problem->rule) {
            ProgramRule::FunctionStatement => match ($problem->subject) {
                'Dynamic SQL' => 0,
                'RESET' => 4,
                default => 5,
            },
            ProgramRule::ResultSet => 1,
            ProgramRule::AutocommitInFunction => 2,
            ProgramRule::CommitInFunction => 3,
            ProgramRule::IncorrectName, ProgramRule::NameTooLong, ProgramRule::DuplicateParameter, ProgramRule::DuplicateVariable,
            ProgramRule::DuplicateCondition, ProgramRule::DuplicateCursor, ProgramRule::RedefinedLabel, ProgramRule::EndLabelMismatch,
            ProgramRule::LeaveWithoutLabel, ProgramRule::IterateWithoutLabel, ProgramRule::UndefinedCondition, ProgramRule::UndefinedCursor,
            ProgramRule::UndeclaredVariable, ProgramRule::ReturnOutsideFunction, ProgramRule::MissingReturn, ProgramRule::DeclarationAfterCursorOrHandler,
            ProgramRule::CursorAfterHandler, ProgramRule::BadSqlState, ProgramRule::ZeroErrorCode, ProgramRule::DuplicateHandler,
            ProgramRule::SignalConditionKind, ProgramRule::DuplicateSignalItem, ProgramRule::BadStatement, ProgramRule::RecursiveCreate,
            ProgramRule::NestedAlterOrDrop, ProgramRule::EventRecursion, ProgramRule::OldRowUpdate, ProgramRule::AfterRowUpdate,
            ProgramRule::NoNewRow, ProgramRule::ArrayCast => 6,
        };
    }
}
