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
 * Orders table-definition grammar conditions, retained headers and refused operands.
 * The input text is used only at the reading boundary, never by execution.
 *
 * @visibility MySqlMemory
 */
final class TableInput
{
    /**
     * Preserves a completed stored CREATE header before its contextual operand notice.
     */
    public function diagnostic(RefusedCreateTail $header, Session $session): SqlError
    {
        try {
            (new StorageOptions())->header($header, $session, new Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant()));
            return (new \MySqlMemory\Session\Problem\Errors())->error($header->problem, $session);
        } catch (SqlError $error) {
            return $error;
        }
    }

    /**
     * Resolves a complete header after grammar conditions and before tail dependencies.
     *
     * @throws DeferredPreparation When preparation retains a partition condition for execution
     */
    public function refused(RejectedInput $failure, string $statement, Session $session): SqlError
    {
        if ($failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\RefusedIndexTemplate) {
            return (new IndexInput())->refused($failure, $statement, $session);
        }
        $header = $failure->problem;
        \SqlSemantics\Diagnostic\Check::invariant($header instanceof RefusedCreateTail, 'Table input retains the completed CREATE header.');
        $database = (new \MySqlMemory\Session\Parse\RoutinePrefix())->database($statement, $failure->end, $session);
        if ($database !== null) {
            return $database;
        }
        if ($header->problem instanceof \SqlSemantics\Platform\MySql\Statement\Partition\Problem\DefinitionIssue || $header->problem instanceof \SqlSemantics\Platform\MySql\Statement\Name\Problem\NameTooLong) {
            return (new \MySqlMemory\Session\Parse\Reader())->rejection(new RejectedInput($header->problem, $failure->end, $failure->aborts), $statement, $session);
        }
        $syntax = $this->warnings($header->message(), $statement, $session, $failure->aborts ? $failure->end : null);
        if ($syntax !== null) {
            return $syntax;
        }
        try {
            $error = $this->resolve($header, $failure, $statement, $session);
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
     * Resolves header options and operand conditions before retaining a prepared definition.
     *
     * @throws SqlError When the completed header or operand is refused
     * @throws DeferredPreparation When invocation owns the remaining partition condition
     */
    public function resolve(RefusedCreateTail $header, RejectedInput $failure, string $statement, Session $session): SqlError
    {
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
        $storage = new StorageOptions();
        $storage->header($header, $session, $context);
        if ($header->problem instanceof \SqlSemantics\Platform\MySql\Statement\Partition\Problem\InvalidPartitionExpression) {
            $operand = new \MySqlMemory\Session\Parse\Entry\Partition\Operand();
            foreach ($operand->notices($header->problem, $session) as $notice) {
                $session->diagnostics->warning($notice->code(), $notice->message());
            }
            throw $operand->error($header->problem, $failure->sources, $session);
        }
        $located = (new \MySqlMemory\Session\Parse\LocatedErrors())->located($header->problem, $failure->sources, $session);
        if ($located !== null) {
            throw $located;
        }
        $this->partition($header, $statement, $session);
        $storage->engine($header, $session->variables, $context);
        if ($session->preparation->inputPrepared && $header->problem instanceof \SqlSemantics\Platform\MySql\Statement\Partition\Problem\RefusedScalar) {
            throw new DeferredPreparation(new \MySqlMemory\Command\Prepared\PendingTable($header, $session->variables->database));
        }
        if ($header->problem instanceof \SqlSemantics\Platform\MySql\Statement\Partition\Problem\RefusedScalar && $session->instance->dictionary->schema($header->name->schema->value ?? $session->variables->database) === null) {
            throw \MySqlMemory\Error\Family\QueryError::BadDatabase->error($header->name->schema->value ?? $session->variables->database);
        }
        return (new \MySqlMemory\Session\Parse\Entry\InputErrors())->refused(new RejectedInput($header->problem, $failure->end, $failure->aborts), $statement, $session);
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
     * Records the selected release's completed definition notices before execution.
     * A legacy cache conflict stops at its second modifier; modern-only notices
     * never enter the legacy diagnostic sequence.
     */
    public function warnings(string $message, string $statement, Session $session, ?int $boundary = null): ?SqlError
    {
        $parser = $session->semantics()->parser();
        \SqlSemantics\Diagnostic\Check::invariant($parser instanceof \SqlParser\Parser\ReductionParser, 'The MySQL parser reports completed productions.');
        $tokens = $parser->tokenize($statement);
        $end = $boundary ?? strlen($statement);
        $fallback = new SqlError(StatementError::UnknownError, $message);
        $notices = (new SyntaxWarnings())->completed($parser, $statement, $end, $session->semantics()->profile(), $session->resolution()->replicaNetTimeout, $session->resolution()->client);
        $conflict = null;
        if ($session->settings()->legacy()) {
            $modifiers = new \MySqlMemory\Session\Parse\Modifiers($tokens, $session->settings()->release());
            [$operators, $conflict] = $modifiers->scan($end, $end);
            $notices = array_values(array_filter($notices, static fn (array $notice): bool => $notice[0] <= ($modifiers->stoppedAt ?? $end)));
        } else {
            $operators = (new ModernWarnings())->before($tokens, $statement, $end, $parser);
        }
        $notices = (new ModernWarnings())->merge($notices, $operators, $tokens);
        usort($notices, static fn (array $left, array $right): int => $left[0] <=> $right[0]);
        $syntax = (new ModernWarnings())->recorded(array_column($notices, 1), $conflict === null ? $fallback : (new \MySqlMemory\Session\CacheOptions())->refusal($conflict), $session, $conflict !== null);
        return $conflict !== null || array_filter($notices, static fn (array $notice): bool => $notice[1] instanceof ParseFailure) !== [] ? $syntax : null;
    }

    /**
     * Selects typed table-definition refusals whose header precedes later conditions.
     */
    public function accepts(\SqlSemantics\Statement\Fact\Diagnostic $problem): bool
    {
        return $problem instanceof RefusedCreateTail || $problem instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\RefusedIndexTemplate;
    }

}
