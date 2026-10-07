<?php

declare(strict_types=1);

namespace MySqlMemory\Session;

use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Error\SqlError;
use SqlSemantics\Platform\MySql\Statement\Alter\Problem\TableExists;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\WrongArgumentCount;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\ValueCountMismatch;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CountedList;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CountMismatch;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\UnknownQualifier;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\MultiplePrimaryKeys;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\NoColumns;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Column\AmbiguousColumn;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Table\MissingTable;

/**
 * Raises the first problem SQL Semantics found in a statement as the error the server reports for it.
 *
 * @visibility MySqlMemory\Session
 */
final class Problems
{
    /**
     * Raises the error of the first diagnostic of an operation, if any.
     *
     * @throws SqlError When the operation has a diagnostic
     */
    public function raise(Operation $operation, Session $session): void
    {
        $diagnostics = $operation->facts->diagnostics;
        if ($diagnostics === []) {
            return;
        }
        throw $this->error($diagnostics[0], $session);
    }

    /**
     * Answers the server error of a diagnostic.
     */
    public function error(Diagnostic $diagnostic, Session $session): SqlError
    {
        $database = $session->variables->database;

        return match (true) {
            $diagnostic instanceof MissingTable => $diagnostic->name->schema === null && $database === '' ? ErrorCode::NoDatabase->error() : ErrorCode::NoSuchTable->error($diagnostic->name->schema?->value ?? $database, $diagnostic->name->name->value),
            $diagnostic instanceof MissingColumn => ErrorCode::BadField->error(($diagnostic->qualifier === null ? '' : $diagnostic->qualifier->name->value . '.') . $diagnostic->name->value, 'field list'),
            $diagnostic instanceof AmbiguousColumn => ErrorCode::NonUniqueColumn->error($diagnostic->name->value, 'field list'),
            $diagnostic instanceof NonUniqueTable => ErrorCode::NonUniqueTable->error($diagnostic->alias->value),
            $diagnostic instanceof ValueCountMismatch => $diagnostic->row === null ? ErrorCode::WrongValueCount->error() : ErrorCode::WrongValueCountOnRow->error($diagnostic->row),
            $diagnostic instanceof OperandColumns => ErrorCode::OperandColumns->error($diagnostic->expected),
            $diagnostic instanceof WrongArgumentCount => ErrorCode::WrongParameterCountToNativeFunction->error($diagnostic->function->value),
            $diagnostic instanceof MultiplePrimaryKeys => ErrorCode::MultiplePrimaryKey->error(),
            $diagnostic instanceof NoColumns => ErrorCode::TableMustHaveColumns->error(),
            $diagnostic instanceof TableExists => ErrorCode::TableExists->error($diagnostic->name->name->value),
            $diagnostic instanceof NotSupportedYet => ErrorCode::NotSupportedYet->error($diagnostic->feature),
            $diagnostic instanceof Misuse => $this->misuse($diagnostic->rule),
            $diagnostic instanceof UnknownQualifier => ErrorCode::BadTable->error($diagnostic->table->name->value),
            $diagnostic instanceof CountMismatch => match ($diagnostic->list) {
                CountedList::SetOperands, CountedList::IntoVariables => ErrorCode::WrongNumberOfColumnsInSelect->error(),
                CountedList::ValueRows => ErrorCode::WrongValueCountOnRow->error(1),
                CountedList::DerivedColumns => ErrorCode::ViewWrongList->error(),
            },
            default => new SqlError(ErrorCode::UnknownError, $diagnostic->message()),
        };
    }

    /**
     * Answers the server error of a misuse rule.
     */
    public function misuse(MisuseRule $rule): SqlError
    {
        return match ($rule) {
            MisuseRule::StarWithoutTables => ErrorCode::NoTablesUsed->error(),
            MisuseRule::DerivedWithoutAlias => ErrorCode::DerivedMustHaveAlias->error(),
            default => new SqlError(ErrorCode::UnknownError, $rule->value),
        };
    }
}
