<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Definition;

use MySqlMemory\Command\Definition\StorageOptions;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Session\Parse\ModernWarnings;
use MySqlMemory\Session\Session;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\RefusedCreateTail;

/**
 * Orders CREATE TABLE grammar conditions, retained header and refused tail.
 * The input text is used only at the reading boundary, never by execution.
 *
 * @visibility MySqlMemory
 */
final class TableInput
{
    /**
     * Resolves a complete header after grammar conditions and before tail dependencies.
     *
     * @throws DeferredPreparation When preparation retains a partition condition for execution
     */
    public function refused(RejectedInput $failure, string $statement, Session $session): SqlError
    {
        if ($failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\RefusedCreateTemplate) {
            return $this->template($failure, $statement, $session);
        }
        $header = $failure->problem;
        \SqlSemantics\Diagnostic\Check::invariant($header instanceof RefusedCreateTail, 'Table input retains the completed CREATE header.');
        if ($header->problem instanceof \SqlSemantics\Platform\MySql\Statement\Partition\Problem\DefinitionIssue || $header->problem instanceof \SqlSemantics\Platform\MySql\Statement\Name\Problem\NameTooLong) {
            return (new \MySqlMemory\Session\Parse\Reader())->rejection(new RejectedInput($header->problem, $failure->end, $failure->aborts), $statement, $session);
        }
        $parser = $session->semantics()->parser();
        \SqlSemantics\Diagnostic\Check::invariant($parser instanceof \SqlParser\Parser\ReductionParser, 'The MySQL parser reports completed productions.');
        $notices = (new SyntaxWarnings())->completed($parser, $statement, strlen($statement), $session->semantics()->profile(), $session->resolution()->replicaNetTimeout, $session->resolution()->client);
        foreach ((new ModernWarnings())->before($parser->tokenize($statement), $statement, strlen($statement), $parser) as $offset => $notice) {
            $notices[] = [$offset, $notice];
        }
        usort($notices, static fn (array $left, array $right): int => $left[0] <=> $right[0]);
        $syntax = (new ModernWarnings())->recorded(array_column($notices, 1), new SqlError(StatementError::UnknownError, $header->message()), $session, false);
        if (array_filter($notices, static fn (array $notice): bool => $notice[1] instanceof ParseFailure) !== []) {
            return $syntax;
        }
        try {
            $context = new Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
            $storage = new StorageOptions();
            $storage->header($header, $session, $context);
            $this->partition($header, $statement, $session);
            $storage->engine($header, $session->variables, $context);
            if ($session->preparation->inputPrepared && $header->problem instanceof \SqlSemantics\Platform\MySql\Statement\Partition\Problem\RefusedScalar) {
                throw new DeferredPreparation(new \MySqlMemory\Command\Prepared\PendingTable($header, $session->variables->database));
            }
            if ($header->problem instanceof \SqlSemantics\Platform\MySql\Statement\Partition\Problem\RefusedScalar && $session->instance->dictionary->schema($header->name->schema->value ?? $session->variables->database) === null) {
                throw \MySqlMemory\Error\Family\QueryError::BadDatabase->error($header->name->schema->value ?? $session->variables->database);
            }
            $error = (new \MySqlMemory\Session\Parse\Entry\InputErrors())->refused(new RejectedInput($header->problem, $failure->end, $failure->aborts), $statement, $session);
        } catch (SqlError $error) {
            // Header failures precede the retained tail; preserve warnings already recorded.
        }
        if (!$error->recorded) {
            $session->diagnostics->error($error->getCode(), $error->getMessage(), $error->signalled);
        }
        foreach ($error->following as [$code, $message]) {
            $session->diagnostics->error($code, $message);
        }
        return new SqlError($error->error, $error->getMessage(), $failure, [], $error->signalled, $error->getCode(), true);
    }

    /**
     * Formats a partition contextualization condition before engine substitution.
     *
     * @throws SqlError When the retained operand has a contextual restriction
     */
    public function partition(RefusedCreateTail $header, string $statement, Session $session): void
    {
        $problem = $header->problem;
        if (!$problem instanceof \SqlSemantics\Platform\MySql\Statement\Partition\Problem\RefusedScalar || $problem->condition === \SqlSemantics\Platform\MySql\Statement\Partition\Problem\OperandCondition::Function) {
            return;
        }
        if ($problem->condition === \SqlSemantics\Platform\MySql\Statement\Partition\Problem\OperandCondition::Subquery) {
            throw (new \MySqlMemory\Session\Syntax($session->resolution()->client))->near($problem->offset, $statement, $session->following, new RejectedInput($problem, $problem->offset));
        }
        $tail = mb_strcut(rtrim(substr($statement, $problem->offset) . $session->following), 0, 80, 'UTF-8');
        $line = substr_count(ltrim(substr($statement, 0, $problem->offset)), "\n") + 1;
        throw new SqlError(StatementError::ParseError, \MySqlMemory\Error\Family\PartitionError::ConstantFunction->message() . " near '" . $tail . "' at line " . $line);
    }

    /**
     * Retains a complete table template across PREPARE or reports its reached input condition.
     *
     * @throws DeferredPreparation When the header and source have prepared successfully
     * @throws CompletedTemplate When a conditional table definition completes
     */
    public function template(RejectedInput $failure, string $statement, Session $session): SqlError
    {
        $problem = $failure->problem;
        \SqlSemantics\Diagnostic\Check::invariant($problem instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\RefusedCreateTemplate, 'The table refusal retains its complete template.');
        $parser = $session->semantics()->parser();
        \SqlSemantics\Diagnostic\Check::invariant($parser instanceof \SqlParser\Parser\ReductionParser, 'The MySQL parser reports completed productions.');
        $notices = (new SyntaxWarnings())->completed($parser, $statement, strlen($statement), $session->semantics()->profile(), $session->resolution()->replicaNetTimeout, $session->resolution()->client);
        foreach ((new ModernWarnings())->before($parser->tokenize($statement), $statement, strlen($statement), $parser) as $offset => $notice) {
            $notices[] = [$offset, $notice];
        }
        usort($notices, static fn (array $left, array $right): int => $left[0] <=> $right[0]);
        $syntax = (new ModernWarnings())->recorded(array_column($notices, 1), new SqlError(StatementError::UnknownError, $problem->message()), $session, false);
        if (array_filter($notices, static fn (array $notice): bool => $notice[1] instanceof ParseFailure) !== []) {
            return $syntax;
        }
        try {
            $use = new \MySqlMemory\Program\Expression\TableUse();
            if ($session->preparation->inputPrepared) {
                $query = $use->prepare($problem->definition, $session);
                $bindings = $query === null ? null : \MySqlMemory\Command\Prepared\ParameterBindings::capture($query);
                throw new DeferredPreparation(new \MySqlMemory\Command\Prepared\PendingTable($problem->definition, $session->variables->database, $bindings));
            }
            throw new CompletedTemplate($use->raise($problem->definition, $session, false));
        } catch (SqlError $error) {
            if (!$error->recorded) {
                $session->diagnostics->error($error->getCode(), $error->getMessage(), $error->signalled);
            }
            foreach ($error->following as [$code, $message]) {
                $session->diagnostics->error($code, $message);
            }
            return new SqlError($error->error, $error->getMessage(), $failure, [], $error->signalled, $error->getCode(), true);
        }
    }


    /**
     * Selects typed table-definition refusals whose header precedes later conditions.
     */
    public function accepts(\SqlSemantics\Statement\Fact\Diagnostic $problem): bool
    {
        return $problem instanceof RefusedCreateTail || $problem instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\RefusedCreateTemplate;
    }

}
