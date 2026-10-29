<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Partition\Problem\InvalidPartitionExpression;
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;
use SqlSemantics\Statement\Fact\Diagnostic;

/**
 * Formats semantic diagnostics that quote their occurrence in the original parser input.
 *
 * Source ranges belong to the analyzed operation; equal expressions elsewhere in a statement
 * cannot supply the location. A directly constructed operation has no original input location.
 *
 * @visibility MySqlMemory
 */
final class LocatedErrors
{
    /**
     * Formats a parse condition after its preceding database and storage checks.
     */
    public function parsed(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure $warning, MySqlStatement $operation, Session $session): SqlError
    {
        if ($warning->problem instanceof \SqlSemantics\Platform\MySql\Statement\Alter\Problem\RepeatedTable && $operation instanceof \SqlSemantics\Platform\MySql\Statement\Alter\DropTable) {
            (new \MySqlMemory\Session\Access\DefaultDatabase())->beforeRepeatedDrop($operation, $warning->problem, $session);
        }
        if ($warning->problem instanceof \SqlSemantics\Platform\MySql\Statement\Account\Problem\RefusedColumnLevel) {
            $database = match (true) {
                $operation instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure,
                $operation instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction,
                $operation instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateTrigger,
                $operation instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateEvent,
                $operation instanceof \SqlSemantics\Platform\MySql\Statement\Routine\AlterEvent => $operation->name->schema->value ?? $session->variables->database,
                default => $session->variables->database,
            };
            return (new Entry\PrivilegeInput())->target($warning->problem, $session, $database);
        }
        if ($operation instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges && $warning->problem instanceof \SqlSemantics\Platform\MySql\Statement\Account\Problem\MisplacedPrivilege) {
            return (new Entry\PrivilegeInput())->level($operation, $session);
        }
        if (\MySqlMemory\Session\Problem\Stages::locked($warning->problem)) {
            (new \MySqlMemory\Session\Access\DefaultDatabase())->referenced($operation, $session);
        }
        if ($warning->problem instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteMisuse && $warning->problem->rule === \SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteRule::NonUpdatableTarget) {
            (new \MySqlMemory\Session\Access\DefaultDatabase())->referenced($operation, $session);
        }
        (new \MySqlMemory\Command\Definition\StorageOptions())->beforePartition($warning, $operation, $session);
        (new \MySqlMemory\Session\Access\TableCreation())->database($operation, $session, $warning->phase === \SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase::Contextualization);
        if ($warning->problem instanceof \SqlSemantics\Platform\MySql\Statement\Partition\Problem\DefinitionIssue) {
            $definition = $operation->statementInformation()->warningSubject($warning);
            return (new Entry\PartitionInput())->bound($warning->problem, $definition instanceof \SqlSemantics\Platform\MySql\Statement\Partition\PartitionDefinition ? $definition->boundarySource : null, $session);
        }
        $instruction = $operation->statementInformation()->warningInstruction($warning) ?? $operation;
        if ($warning->problem instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\InvalidColumnAttribute && \MySqlMemory\Command\Dispatcher::program($operation)) {
            return (new \MySqlMemory\Session\Problem\Errors())->error($warning->problem, $session, 'field list', $operation);
        }
        return (new \MySqlMemory\Session\Problem\Precision())->legacy($this->error($warning->problem, $operation, $session) ?? (new \MySqlMemory\Session\CacheOptions())->placed($warning->problem, $operation, $session) ?? (new \MySqlMemory\Session\Problem\Errors())->error($warning->problem, $session, 'field list', $instruction), $session->settings()->release());
    }

    /**
     * Answers the original SQL tail and line of a forbidden construct, or null for another diagnostic.
     */
    public function error(Diagnostic $problem, MySqlStatement $operation, Session $session): ?SqlError
    {
        return $this->located($problem, null, $session);
    }

    /**
     * Formats an occurrence from either an operation or a retained input refusal.
     */
    public function located(Diagnostic $problem, ?\SqlSemantics\Statement\Source\SourceMap $sources, Session $session): ?SqlError
    {
        if ($problem instanceof \SqlSemantics\Platform\MySql\Statement\Server\Problem\RefusedXaIdentifier) {
            return (new Entry\XaInput())->error($problem, $session);
        }
        if ($problem instanceof \SqlSemantics\Platform\MySql\Statement\Type\Problem\CastSizeInput) {
            return (new Entry\InputErrors())->castSize($problem, $session->text, $session);
        }
        if ($problem instanceof \SqlSemantics\Platform\MySql\Statement\Partition\Problem\RefusedScalar && $problem->condition === \SqlSemantics\Platform\MySql\Statement\Partition\Problem\OperandCondition::Subquery) {
            $origin = $problem->operand->source() ?? $sources?->of($problem->operand);
            $offset = $this->subquery($problem->operand, $sources) ?? $origin?->offset;
            return $offset === null ? new SqlError(StatementError::ParseError, $problem->message()) : (new \MySqlMemory\Session\Syntax($session->resolution()->client))->near($offset, $session->text, $session->following, new \SqlSemantics\Construction\RejectedInput($problem, $offset));
        }
        if (!$problem instanceof InvalidPartitionExpression) {
            return null;
        }
        $origin = $problem->expression->source() ?? $sources?->of($problem->expression);
        if ($origin === null) {
            return new SqlError(StatementError::ParseError, $problem->message());
        }
        $offset = $origin->offset;
        if (!$session->settings()->legacy() && (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\PartitionExpressions())->condition($problem->expression, $session->settings()->release()) === \SqlSemantics\Platform\MySql\Statement\Partition\Problem\OperandCondition::Subquery) {
            $offset = $this->subquery($problem->expression, $sources) ?? $offset;
            return (new \MySqlMemory\Session\Syntax($session->resolution()->client))->near($offset, $session->text, $session->following, new \SqlSemantics\Construction\RejectedInput($problem, $offset));
        }
        if (in_array($session->settings()->release(), [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true)) {
            $offset += $origin->length;
            foreach ($session->semantics()->parser()->tokenize($session->text) as $token) {
                if ($token->offset >= $offset && $token->text !== '') {
                    $offset = $token->offset;
                    break;
                }
            }
        }
        $tail = mb_strcut(rtrim(substr($session->text, $offset) . $session->following), 0, 80, 'UTF-8');
        $line = substr_count(ltrim(substr($session->text, 0, $offset)), "\n") + 1;

        return new SqlError(StatementError::ParseError, $problem->message() . " near '" . $tail . "' at line " . $line);
    }

    /**
     * Locates the first query occurrence rather than its enclosing scalar function.
     */
    public function subquery(\SqlSemantics\Statement\Operand $operand, ?\SqlSemantics\Statement\Source\SourceMap $sources): ?int
    {
        $offsets = [];
        $graph = new \SqlSemantics\Validation\ValueGraph(['SqlSemantics\\Statement\\', 'SqlSemantics\\Contract\\', 'SqlSemantics\\Platform\\MySql\\Statement\\']);
        foreach ($graph->objects($operand) as $part) {
            $origin = $part instanceof \SqlSemantics\Statement\Query ? ($part->source() ?? $sources?->of($part)) : null;
            if ($origin !== null) {
                $offsets[] = $origin->offset;
            }
        }
        return $offsets === [] ? null : min($offsets);
    }

}
