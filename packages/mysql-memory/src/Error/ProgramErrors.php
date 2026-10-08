<?php

declare(strict_types=1);

namespace MySqlMemory\Error;

use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\KindRefusal;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\WrongRelationKind;

/**
 * Answers the server error of a rule a stored program or a condition statement breaks.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/stored-program-restrictions.html,
 * https://dev.mysql.com/doc/refman/8.4/en/signal.html.
 *
 * @visibility MySqlMemory
 */
final class ProgramErrors
{
    /**
     * Answers the error of a program problem, with the message SQL Semantics wrote for it.
     */
    public function error(ProgramProblem $problem): SqlError
    {
        return new SqlError($this->code($problem->rule), $problem->message());
    }

    /**
     * Answers the error of a name that resolves to a relation of another kind than the statement needs: a base table where it needs a view, or a view where it needs a base table.
     *
     * Source: https://dev.mysql.com/doc/refman/8.4/en/drop-view.html.
     *
     * @param string $database The current database, for a name without one
     */
    public function relation(WrongRelationKind $problem, string $database): SqlError
    {
        $schema = $problem->name->schema->value ?? $database;
        $name = $problem->name->name->value;

        return match ($problem->refusal) {
            KindRefusal::NotView => ErrorCode::WrongObject->error($schema, $name, 'VIEW'),
            KindRefusal::NotBaseTable => ErrorCode::WrongObject->error($schema, $name, 'BASE TABLE'),
            KindRefusal::UnknownTable => ErrorCode::BadTable->error($schema . '.' . $name),
            KindRefusal::NoSuchTable => ErrorCode::NoSuchTable->error($schema, $name),
        };
    }

    /**
     * Answers the error number of a program rule.
     */
    public function code(ProgramRule $rule): ErrorCode
    {
        return match ($rule) {
            ProgramRule::DuplicateParameter => ErrorCode::DuplicateParameter,
            ProgramRule::DuplicateVariable => ErrorCode::DuplicateVariable,
            ProgramRule::DuplicateCondition => ErrorCode::DuplicateCondition,
            ProgramRule::DuplicateCursor => ErrorCode::DuplicateCursor,
            ProgramRule::RedefinedLabel => ErrorCode::LabelRedefined,
            ProgramRule::EndLabelMismatch => ErrorCode::EndLabelMismatch,
            ProgramRule::LeaveWithoutLabel, ProgramRule::IterateWithoutLabel => ErrorCode::LabelMissing,
            ProgramRule::UndefinedCondition => ErrorCode::UndefinedCondition,
            ProgramRule::UndefinedCursor => ErrorCode::UndefinedCursor,
            ProgramRule::UndeclaredVariable => ErrorCode::UndeclaredVariable,
            ProgramRule::ReturnOutsideFunction => ErrorCode::ReturnOutsideFunction,
            ProgramRule::MissingReturn => ErrorCode::MissingReturn,
            ProgramRule::DeclarationAfterCursorOrHandler => ErrorCode::DeclarationAfterHandler,
            ProgramRule::CursorAfterHandler => ErrorCode::CursorAfterHandler,
            ProgramRule::BadSqlState => ErrorCode::BadSqlState,
            ProgramRule::ZeroErrorCode => ErrorCode::WrongValue,
            ProgramRule::DuplicateHandler => ErrorCode::DuplicateHandler,
            ProgramRule::SignalConditionKind => ErrorCode::SignalConditionKind,
            ProgramRule::DuplicateSignalItem => ErrorCode::DuplicateSignalItem,
            ProgramRule::BadStatement => ErrorCode::ProgramStatement,
            ProgramRule::RecursiveCreate => ErrorCode::RecursiveCreate,
            ProgramRule::NestedAlterOrDrop => ErrorCode::NestedProgramChange,
            ProgramRule::EventRecursion => ErrorCode::EventRecursion,
            ProgramRule::ResultSet => ErrorCode::ResultSetFromProgram,
            ProgramRule::CommitInFunction => ErrorCode::CommitInFunction,
            ProgramRule::FunctionStatement => ErrorCode::FunctionStatement,
            ProgramRule::OldRowUpdate, ProgramRule::AfterRowUpdate => ErrorCode::TriggerRowChange,
            ProgramRule::NoNewRow => ErrorCode::TriggerRowMissing,
        };
    }
}
