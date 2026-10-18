<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Entry;

use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use RuntimeException;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\RoleOrPrivilegeMismatch;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSourceNumber;
use SqlSemantics\Statement\Fact\Diagnostic;

/**
 * Dispatches typed input refusals, formatting syntax excerpts only when needed.
 *
 * SQL text is confined to input formatting and is never used to execute a structure.
 *
 * @visibility MySqlMemory
 */
final class InputErrors
{
    /**
     * Excludes a refusal at the lookahead token from its own preceding notices.
     */
    public function noticeEnd(\SqlSemantics\Construction\RejectedInput $failure): int
    {
        return $failure->end - ($failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Account\Problem\RejectedPasswordHash ? 1 : 0);
    }

    /**
     * Formats a refusal whose excerpt boundary belongs to the input exception.
     */
    public function refused(\SqlSemantics\Construction\RejectedInput $failure, string $statement, Session $session): SqlError
    {
        if ($failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Account\Problem\RejectedPasswordHash) {
            return (new \MySqlMemory\Session\Syntax(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::named((string) $session->variables->read('character_set_client'))))->near($failure->end, $statement, $session->following, $failure, true, $session->semantics()->parser());
        }

        return (new \MySqlMemory\Session\Problem\Errors())->error($failure->problem, $session);
    }

    /**
     * Formats an input refusal, or leaves other diagnostics to their semantic family.
     */
    public function error(Diagnostic $diagnostic, Session $session): ?SqlError
    {
        return match (true) {
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Server\Database\Problem\InvalidUpgradeName => str_starts_with($diagnostic->name->value, '#mysql50#') ? ($diagnostic->identifierTooLong() ? SchemaError::TooLongIdentifier : SchemaError::WrongDatabaseName)->error((new \MySqlMemory\Error\DiagnosticText())->bytes($diagnostic->name->value, 100, true)) : \MySqlMemory\Error\Family\StatementError::WrongUsage->error('ALTER DATABASE UPGRADE DATA DIRECTORY NAME', 'name'),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedProgramBody => (new ProgramInput())->error($diagnostic, $session),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Account\Problem\RoutineColumnPrivilege => (new PrivilegeInput())->columns($diagnostic, $session),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Account\Problem\RefusedColumnLevel => (new PrivilegeInput())->target($diagnostic, $session),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Account\Problem\MisplacedPrivilege,
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Account\Problem\RefusedGrantLevel => (new PrivilegeInput())->placement($diagnostic, $session),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedConnectionExplain,
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedExplainInto => (new ExplainInput())->error($diagnostic),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Problem\EvaluationWildcard => (new EvaluationInput())->error($diagnostic, $session),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedTemporalLiteral && $diagnostic->form === \SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm::Timestamp => \MySqlMemory\Evaluation\Compile\Family\TimestampLiteral::error($diagnostic->text, $session->modes()),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Name\EmptyObjectName => $this->name($diagnostic),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedSignalItems => (new \MySqlMemory\Command\Condition\SignalProblems())->refused($diagnostic, $session),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedNamesAssignment => (new NamesInput())->error($diagnostic, $session),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Server\Problem\RefusedXaIdentifier => (new XaInput())->error($diagnostic, $session),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Problem\GeneratedColumnEntry,
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedLogSubquery => (new \MySqlMemory\Session\Syntax(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::named((string) $session->variables->read('character_set_client'))))->near($diagnostic->offset, $session->text, $session->following, new RuntimeException($diagnostic->message()), true, $session->semantics()->parser()),
            default => $this->query($diagnostic, $session) ?? $this->source($diagnostic, $session),
        };
    }

    /**
     * Maps typed query-input conditions before resolving other input families.
     */
    public function query(Diagnostic $diagnostic, Session $session): ?SqlError
    {
        return match (true) {
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedQuery => $this->view($diagnostic, $session),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedScalar => $this->scalar($diagnostic, $session),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedEvaluation => (new EvaluationInput())->deferred($diagnostic, $session),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedQuery => $this->selection($diagnostic->query, $session),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedSelection => $this->selection($diagnostic->selection, $session),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\Option\RestrictedSelectOption => \MySqlMemory\Error\Family\StatementError::CantUseOptionHere->error($diagnostic->option->value),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\UnenclosedUnion => $this->union($diagnostic, $session),
            default => null,
        };
    }

    /**
     * Opens read dependencies and the ALTER target before preparing a refused view body.
     */
    public function view(\SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedQuery $problem, Session $session): SqlError
    {
        $query = $problem->query instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedQuery ? $problem->query->query : $problem->query->selection;
        $database = $problem->view->schema->value ?? $session->variables->database;
        if ($database === '') {
            return \MySqlMemory\Error\Family\QueryError::NoDatabase->error();
        }
        $context = $session->semantics()->context($session->instance->dictionary->declarations(), true, $session->variables->database === '' ? null : new \SqlSemantics\Contract\SearchPath($session->variables->database), $session->resolution());
        $derivation = new \SqlSemantics\Construction\Derivation($context);
        $query->deriveProgram($derivation, new \SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope($derivation->environment()));
        (new \MySqlMemory\Program\Expression\SelectionUse())->diagnosed($derivation->facts(), $session);
        if ($problem->alter) {
            $schema = $session->instance->dictionary->schema($database);
            $name = $problem->view->name->value;
            if ($schema?->table($name) !== null) {
                return SchemaError::WrongObject->error($database, $name, 'VIEW');
            }
            if (!isset($schema->views[$name])) {
                return \MySqlMemory\Error\Family\QueryError::NoSuchTable->error($database, $name);
            }
        }
        return $this->selection($query, $session, true);
    }

    /**
     * Resolves a refused operand without discarding its earlier dependency conditions.
     */
    public function scalar(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedScalar $problem, Session $session): SqlError
    {
        try {
            (new \MySqlMemory\Program\Expression\RowUse())->raise($problem->operand, $session, false);
        } catch (SqlError $error) {
            return $error;
        }
    }

    /**
     * Resolves a retained selection condition without bypassing input notice ordering.
     */
    public function selection(\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredQuery $selection, Session $session, bool $viewDefinition = false): SqlError
    {
        try {
            if ($selection instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredQuery) {
                (new \MySqlMemory\Program\Expression\QueryUse())->raise($selection, $session, false);
            }
            (new \MySqlMemory\Program\Expression\SelectionUse())->raise($selection, $session, false, $viewDefinition);
        } catch (SqlError $error) {
            return $error;
        }
    }

    /**
     * Formats an empty object name according to its semantic role.
     */
    public function name(\SqlSemantics\Platform\MySql\Statement\Name\EmptyObjectName $diagnostic): SqlError
    {
        return match ($diagnostic->kind) {
            \SqlSemantics\Platform\MySql\Statement\Name\ObjectNameKind::Database => SchemaError::WrongDatabaseName->error(''),
            \SqlSemantics\Platform\MySql\Statement\Name\ObjectNameKind::Table => SchemaError::WrongTableName->error(''),
            \SqlSemantics\Platform\MySql\Statement\Name\ObjectNameKind::Server => \MySqlMemory\Error\Family\DataError::WrongValue->error('server name', ''),
        };
    }

    /**
     * Formats literal, numeric, source-option and role input refusals.
     */
    public function source(Diagnostic $diagnostic, Session $session): ?SqlError
    {
        return match (true) {
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Name\Problem\MismatchedDefinitionName => (new Definition\DefinitionNameInput())->error($diagnostic, $session),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Server\Problem\RefusedKillOperand => (new \MySqlMemory\Program\Expression\KillUse())->error($diagnostic->process, $session),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Event\Problem\RefusedOperand => (new Event\Alteration())->operand($diagnostic, $session),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Problem\EmptyEventAlteration => (new Event\Alteration())->error($diagnostic, $session),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedDeleteTargets => (new Dml\Targets())->unmatched($diagnostic, $session),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Problem\RepeatedDeleteTarget => (new Dml\Targets())->error($diagnostic, $session),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\InvalidColumnAttribute => $this->column($diagnostic, $session),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\ZeroKeyPrefix => SchemaError::KeyPartZeroLength->error($diagnostic->column->value),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\RefusedJsonAttribute => (new Json\AttributeInput())->error($diagnostic),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedColumns => ($diagnostic->program ?? $diagnostic->view)->schema === null && $session->variables->database === '' ? \MySqlMemory\Error\Family\QueryError::NoDatabase->error() : SchemaError::WrongColumnName->error($diagnostic->column->value),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSetting => (new Replication\Settings())->error($diagnostic, $session),
            $diagnostic instanceof RefusedSourceNumber => (new SourceNumberInput())->error($diagnostic, $session->text),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Problem\ZeroFileCount => (new SourceNumberInput())->fileCount($diagnostic, $session),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedLoadOptions => (new Load\Options())->error($diagnostic, $session),
            $diagnostic instanceof RoleOrPrivilegeMismatch => (new RoleInput())->error($diagnostic, $session->text),
            default => null,
        };
    }

    /**
     * Resolves a refused column's declaration scope before reporting its attribute conflict.
     */
    public function column(\SqlSemantics\Platform\MySql\Statement\Table\Problem\InvalidColumnAttribute $diagnostic, Session $session): SqlError
    {
        return $diagnostic->scope !== null && $diagnostic->scope->schema === null && $session->variables->database === '' ? \MySqlMemory\Error\Family\QueryError::NoDatabase->error() : (new \MySqlMemory\Error\TypeErrors())->columnAttribute($diagnostic);
    }
    /**
     * Formats an enclosure condition; direct structures have no input excerpt.
     */
    public function union(\SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\UnenclosedUnion $problem, Session $session): SqlError
    {
        if ($problem->offset === null) {
            return \MySqlMemory\Error\Family\StatementError::ParseError->error('', 1);
        }
        $suffix = ltrim(substr($session->text, $problem->offset), " \t\n\r\f\v");
        $offset = strlen($session->text) - (rtrim($suffix) === ';' ? 0 : strlen($suffix));
        return (new \MySqlMemory\Session\Syntax(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::named((string) $session->variables->read('character_set_client'))))->near($offset, $session->text, $session->following, new RuntimeException($problem->message()), true, $session->semantics()->parser());
    }

}
