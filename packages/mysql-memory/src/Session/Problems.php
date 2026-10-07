<?php

declare(strict_types=1);

namespace MySqlMemory\Session;

use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Walker;
use SqlSemantics\Platform\MySql\Statement\Alter\Problem\TableExists;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\WrongArgumentCount;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\UnknownDeleteTable;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\ValueCountMismatch;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\CollationMismatch;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\IllegalCollationMix;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCharset;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\ProgramVariable;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CountedList;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CountMismatch;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\GroupingRule;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\NonGroupedColumn;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\UnknownPartition;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\UnknownQualifier;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\UnpartitionedTable;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\MultiplePrimaryKeys;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\NoColumns;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\VariableMisuse;
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
        foreach ((new Walker())->find($operation->statement, ProgramVariable::class) as $variable) {
            throw ErrorCode::UndeclaredVariable->error($variable->name->value);
        }
        $grouping = $session->modes()->has('ONLY_FULL_GROUP_BY');
        $diagnostics = array_values(array_filter($operation->facts->diagnostics, static fn (Diagnostic $diagnostic): bool => $grouping || !$diagnostic instanceof NonGroupedColumn));
        if ($diagnostics === []) {
            return;
        }
        $locator = (new Locator())->statement($operation->statement);
        $names = [];
        foreach ($locator->nodes() as $node) {
            $resolution = $operation->facts->covers($node) ? $operation->facts->scalar($node)->resolution : null;
            $place = $locator->place($node);
            if ($resolution instanceof Diagnostic && $place !== null) {
                $names[spl_object_id($resolution)] = [$resolution, $place];
            }
        }
        foreach ($diagnostics as $diagnostic) {
            if (!isset($names[spl_object_id($diagnostic)])) {
                throw $this->error($diagnostic, $session, 'field list');
            }
            $first = null;
            foreach ($names as $candidate) {
                if ($first === null || Locator::precedes($candidate[1][1], $first[1][1])) {
                    $first = $candidate;
                }
            }
            assert($first !== null);
            throw $this->error($first[0], $session, $first[1][0]);
        }
    }

    /**
     * Answers the server error of a diagnostic, for a name read in a clause.
     */
    public function error(Diagnostic $diagnostic, Session $session, string $clause = 'field list'): SqlError
    {
        $database = $session->variables->database;

        return match (true) {
            $diagnostic instanceof MissingTable => $diagnostic->name->schema === null && $database === '' ? ErrorCode::NoDatabase->error() : ErrorCode::NoSuchTable->error($diagnostic->name->schema?->value ?? $database, $diagnostic->name->name->value),
            $diagnostic instanceof MissingColumn => ErrorCode::BadField->error(($diagnostic->qualifier === null ? '' : $diagnostic->qualifier->name->value . '.') . $diagnostic->name->value, $clause),
            $diagnostic instanceof AmbiguousColumn => ErrorCode::NonUniqueColumn->error($diagnostic->name->value, $clause),
            $diagnostic instanceof NonUniqueTable => ErrorCode::NonUniqueTable->error($diagnostic->alias->value),
            $diagnostic instanceof ValueCountMismatch => $diagnostic->row === null ? ErrorCode::WrongValueCount->error() : ErrorCode::WrongValueCountOnRow->error($diagnostic->row),
            $diagnostic instanceof OperandColumns => ErrorCode::OperandColumns->error($diagnostic->expected),
            $diagnostic instanceof WrongArgumentCount => ErrorCode::WrongParameterCountToNativeFunction->error($diagnostic->function->value),
            $diagnostic instanceof MultiplePrimaryKeys => ErrorCode::MultiplePrimaryKey->error(),
            $diagnostic instanceof NoColumns => ErrorCode::TableMustHaveColumns->error(),
            $diagnostic instanceof TableExists => ErrorCode::TableExists->error($diagnostic->name->name->value),
            $diagnostic instanceof NotSupportedYet => ErrorCode::NotSupportedYet->error($diagnostic->feature),
            $diagnostic instanceof Misuse => $this->misuse($diagnostic->rule),
            $diagnostic instanceof NonGroupedColumn => new SqlError(match ($diagnostic->rule) {
                GroupingRule::NotDetermined => ErrorCode::WrongFieldWithGroup,
                GroupingRule::WithoutGroupBy => ErrorCode::MixOfGroupFunctionAndFields,
                GroupingRule::NotSelected => ErrorCode::FieldInOrderNotSelect,
            }, $diagnostic->message()),
            $diagnostic instanceof UnknownQualifier => ErrorCode::BadTable->error(($diagnostic->table->schema === null ? '' : $diagnostic->table->schema->value . '.') . $diagnostic->table->name->value),
            $diagnostic instanceof UnknownDeleteTable => ErrorCode::UnknownTable->error($diagnostic->table->name->value, 'MULTI DELETE'),
            $diagnostic instanceof CountMismatch => match ($diagnostic->list) {
                CountedList::SetOperands, CountedList::IntoVariables => ErrorCode::WrongNumberOfColumnsInSelect->error(),
                CountedList::ValueRows => ErrorCode::WrongValueCountOnRow->error(1),
                CountedList::DerivedColumns => ErrorCode::ViewWrongList->error(),
            },
            $diagnostic instanceof IllegalCollationMix => new SqlError(match (count($diagnostic->operands)) {
                2 => ErrorCode::CantAggregateTwoCollations,
                3 => ErrorCode::CantAggregateThreeCollations,
                default => ErrorCode::CantAggregateCollations,
            }, $diagnostic->message()),
            $diagnostic instanceof UnpartitionedTable => ErrorCode::PartitionClauseOnNonpartitioned->error(),
            $diagnostic instanceof UnknownPartition => ErrorCode::UnknownPartition->error($diagnostic->partition, $diagnostic->table),
            $diagnostic instanceof UnknownSystemVariable => ErrorCode::UnknownSystemVariable->error($diagnostic->name),
            $diagnostic instanceof VariableMisuse => new SqlError(ErrorCode::from($diagnostic->rule->code()), $diagnostic->message()),
            $diagnostic instanceof UnknownCollation => ErrorCode::UnknownCollation->error($diagnostic->name),
            $diagnostic instanceof UnknownCharset => ErrorCode::UnknownCharacterSet->error($diagnostic->name),
            $diagnostic instanceof CollationMismatch => ErrorCode::CollationCharsetMismatch->error($diagnostic->collation, $diagnostic->charset),
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
