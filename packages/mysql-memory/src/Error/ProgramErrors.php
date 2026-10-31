<?php

declare(strict_types=1);

namespace MySqlMemory\Error;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\SchemaError;
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
     * Answers the unavailable-language condition in the release's spelling.
     * Observed on MySQL 8.1, 8.2, 8.3, 8.4 and 9.1 without a language component.
     */
    public function language(\SqlSemantics\Contract\GrammarRelease $release): SqlError
    {
        return ProgramError::LanguageComponentUnavailable->error(in_array($release, [\SqlSemantics\Contract\GrammarRelease::MySql810, \SqlSemantics\Contract\GrammarRelease::MySql820, \SqlSemantics\Contract\GrammarRelease::MySql830], true) ? 'No Language component available' : 'Language component: Not available.');
    }

    /**
     * The error number of each program rule, by the name of the rule.
     *
     * It holds every case of ProgramRule.
     */
    public const CODES = [
        'IncorrectName' => ProgramError::IncorrectName,
        'NameTooLong' => SchemaError::TooLongIdentifier,
        'DuplicateParameter' => ProgramError::DuplicateParameter,
        'DuplicateVariable' => ProgramError::DuplicateVariable,
        'DuplicateCondition' => ProgramError::DuplicateCondition,
        'DuplicateCursor' => ProgramError::DuplicateCursor,
        'RedefinedLabel' => ProgramError::LabelRedefined,
        'EndLabelMismatch' => ProgramError::EndLabelMismatch,
        'LeaveWithoutLabel' => ProgramError::LabelMissing,
        'IterateWithoutLabel' => ProgramError::LabelMissing,
        'UndefinedCondition' => ProgramError::UndefinedCondition,
        'UndefinedCursor' => ProgramError::UndefinedCursor,
        'UndeclaredVariable' => ProgramError::UndeclaredVariable,
        'ReturnOutsideFunction' => ProgramError::ReturnOutsideFunction,
        'MissingReturn' => ProgramError::MissingReturn,
        'DeclarationAfterCursorOrHandler' => ProgramError::DeclarationAfterHandler,
        'CursorAfterHandler' => ProgramError::CursorAfterHandler,
        'BadSqlState' => ProgramError::BadSqlState,
        'ZeroErrorCode' => DataError::WrongValue,
        'DuplicateHandler' => ProgramError::DuplicateHandler,
        'SignalConditionKind' => ProgramError::SignalConditionKind,
        'DuplicateSignalItem' => ProgramError::DuplicateSignalItem,
        'BadStatement' => ProgramError::ProgramStatement,
        'RecursiveCreate' => ProgramError::RecursiveCreate,
        'NestedAlterOrDrop' => ProgramError::NestedProgramChange,
        'EventRecursion' => ProgramError::EventRecursion,
        'ResultSet' => ProgramError::ResultSetFromProgram,
        'AutocommitInFunction' => ProgramError::AutocommitInFunction,
        'CommitInFunction' => ProgramError::CommitInFunction,
        'FunctionStatement' => ProgramError::FunctionStatement,
        'OldRowUpdate' => ProgramError::TriggerRowChange,
        'AfterRowUpdate' => ProgramError::TriggerRowChange,
        'NoNewRow' => ProgramError::TriggerRowMissing,
        'ArrayCast' => Family\StatementError::WrongUsage,
    ];

    /**
     * Answers the error of a program problem, with the message SQL Semantics wrote for it.
     */
    public function error(ProgramProblem $problem, \SqlSemantics\Contract\GrammarRelease $release = \SqlSemantics\Contract\GrammarRelease::MySql847): SqlError
    {
        $message = $problem->message();
        if ($problem->rule === ProgramRule::BadSqlState) {
            $subject = explode("\0", $problem->subject ?? '')[0];
            if (in_array($release, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true)) {
                $subject = preg_split('/[\xF0-\xF4][\x80-\xBF]{3}/', $subject)[0] ?? $subject;
            }
            $message = str_replace('%s', $subject, $problem->rule->value);
        }

        if ($problem->rule === ProgramRule::NameTooLong) {
            $legacy = in_array($release, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true);
            $message = str_replace('%s', (new DiagnosticText())->bytes($problem->subject ?? '', 100, $legacy), $problem->rule->value);
        }

        return new SqlError($this->code($problem->rule), $message);
    }

    /**
     * Answers the error of a name that resolves to a relation of another kind than the statement needs: a base table where it needs a view, or a view where it needs a base table.
     *
     * Source: https://dev.mysql.com/doc/refman/8.4/en/drop-view.html.
     *
     * @param string $database The current database, for a name without one
     */
    public function relation(WrongRelationKind $problem, string $database, bool $legacy = false): SqlError
    {
        $schema = $problem->name->schema->value ?? $database;
        $name = $problem->name->name->value;

        return match ($problem->refusal) {
            KindRefusal::NotView => SchemaError::WrongObject->error($schema, $name, 'VIEW'),
            KindRefusal::NotBaseTable => SchemaError::WrongObject->error($schema, $name, 'BASE TABLE'),
            KindRefusal::UnknownTable => SchemaError::BadTable->error((new DiagnosticText())->missingTables($schema . '.' . $name, $legacy)),
            KindRefusal::NoSuchTable => QueryError::NoSuchTable->error($schema, $name),
        };
    }

    /**
     * Answers the error number of a program rule.
     */
    public function code(ProgramRule $rule): ErrorCode
    {
        return self::CODES[$rule->name];
    }
}
