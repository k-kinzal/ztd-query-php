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
     * Formats an input refusal, or leaves other diagnostics to their semantic family.
     */
    public function error(Diagnostic $diagnostic, Session $session): ?SqlError
    {
        return match (true) {
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedProgramBody => (new ProgramInput())->error($diagnostic, $session),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Account\Problem\RoutineColumnPrivilege => (new PrivilegeInput())->columns($diagnostic, $session),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Account\Problem\MisplacedPrivilege,
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Account\Problem\RefusedGrantLevel => (new PrivilegeInput())->placement($diagnostic, $session),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedConnectionExplain,
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedExplainInto => (new ExplainInput())->error($diagnostic),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\Option\RestrictedSelectOption => \MySqlMemory\Error\Family\StatementError::CantUseOptionHere->error($diagnostic->option->value),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Problem\EvaluationWildcard => (new EvaluationInput())->error($diagnostic, $session),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedTemporalLiteral && $diagnostic->form === \SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm::Timestamp => \MySqlMemory\Evaluation\Compile\Family\TimestampLiteral::error($diagnostic->text, $session->modes()),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Name\EmptyObjectName => match ($diagnostic->kind) {
                \SqlSemantics\Platform\MySql\Statement\Name\ObjectNameKind::Database => SchemaError::WrongDatabaseName->error(''),
                \SqlSemantics\Platform\MySql\Statement\Name\ObjectNameKind::Table => SchemaError::WrongTableName->error(''),
            },
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedSignalItems => (new \MySqlMemory\Command\Condition\SignalProblems())->refused($diagnostic, $session),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedNamesAssignment => (new NamesInput())->error($diagnostic, $session),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Server\Problem\RefusedXaIdentifier => (new XaInput())->error($diagnostic, $session),
            $diagnostic instanceof RefusedSourceNumber => (new SourceNumberInput())->error($diagnostic, $session->text),
            $diagnostic instanceof RoleOrPrivilegeMismatch => (new RoleInput())->error($diagnostic, $session->text),
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Problem\GeneratedColumnEntry,
            $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedLogSubquery => (new \MySqlMemory\Session\Syntax(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::named((string) $session->variables->read('character_set_client'))))->near($diagnostic->offset, $session->text, $session->following, new RuntimeException($diagnostic->message()), true, $session->semantics()->parser()),
            default => null,
        };
    }
}
