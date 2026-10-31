<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Prepared;

use LogicException;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Result\Batch;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\Session;
use MySqlMemory\Session\Statement\Binding;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Statement\AnalyzedStatement;

/**
 * A prepared semantic statement with its definition-time name and literal context.
 *
 * Modern executions reuse the prepared structure and its expression information.
 * Legacy executions bind current parameter and user-variable types. Incompatible
 * modern parameters and changed declarations require explicit repreparation.
 * Source text is only an observable request label. SQL modes and
 * exposed session variables retain their execution-time values. The default
 * database and literal encoding retain their preparation-time interpretation.
 * Observed through SQL PREPARE on MySQL 5.6.51, 5.7.44 and 8.4.7.
 *
 * @visibility MySqlMemory
 */
final class PreparedStatement
{
    /**
     * Captures a prepared structure and the inputs needed to interpret its literals.
     *
     * @param string|null $engine The selected table engine, resolved during preparation
     * @param GenerationMetadata|null $generation Destination changes observed by generation declarations
     */
    public function __construct(public readonly AnalyzedStatement $statement, public readonly LanguageProfile $profile, public readonly Settings $literals, public readonly string $database, public readonly ?string $engine = null, public readonly ?GenerationMetadata $generation = null)
    {
    }

    /**
     * Captures the definition without retaining the operation's source map or parser.
     *
     * @throws LogicException When the statement has no preparation implementation
     */
    public static function capture(AnalyzedStatement $operation, Session $session): self
    {

        if (!$operation instanceof MySqlStatement) {
            throw new LogicException('No prepared statement implementation for ' . $operation::class);
        }
        $engine = $operation instanceof \SqlSemantics\Platform\MySql\Statement\Table\CreateTable && array_filter($operation->options, static fn ($option): bool => $option instanceof \SqlSemantics\Platform\MySql\Statement\Table\Option\EngineOption) !== [] ? (new \MySqlMemory\Command\Definition\StorageOptions())->engine($operation, $session->variables) : null;
        $generation = $operation instanceof \SqlSemantics\Platform\MySql\Statement\Table\CreateTable ? GenerationMetadata::capture($operation, $session->variables->database, $session) : null;
        return new self($operation, $operation->profile(), $session->resolution(), $session->variables->database, $engine, $generation);
    }

    /**
     * Executes the prepared structure, retaining modern preparation-time types.
     *
     * @param list<array{int|float|string|\MySqlMemory\Evaluation\ExactDecimal|null, \MySqlMemory\Typing\Domain}> $parameters Bound values and their domains
     * @throws SqlError When the statement fails
     */
    public function execute(Session $session, string $label, array $parameters): Reply
    {
        if ($session->running->using === []) {
            $session->activity->begin();
            \MySqlMemory\Session\State\StatementCounters::received($session);
        }
        $database = $session->variables->database;
        $replayed = $session->replayed;
        $engines = $session->variables->preparedEngines;
        $session->variables->preparedEngines = [];
        $session->variables->database = $this->database;
        $session->replayed = true;
        $session->text = $label;

        $session->dots = [];
        try {
            $this->generation?->refresh($session);

            $operation = $session->settings()->legacy() ? (new Binding())->operation($session, $this->statement, $parameters, $this->profile, $this->literals) : $this->statement;
            $session->variables->preparedEngines = $operation instanceof \SqlSemantics\Platform\MySql\Statement\Table\CreateTable ? [spl_object_id($operation) => $this->engine] : [];
            return (new Execution($session))->statement($operation, $parameters);
        } finally {
            $session->variables->preparedEngines = $engines;
            $session->variables->database = $database;
            $session->replayed = $replayed;
        }
    }

    /**
     * Reconstructs the semantic types when incompatible parameter values require preparation again.
     *
     * @param list<array{int|float|string|\MySqlMemory\Evaluation\ExactDecimal|null, \MySqlMemory\Typing\Domain}> $parameters Current argument values
     */
    public function rebind(Session $session, array $parameters): self
    {
        $database = $session->variables->database;
        $session->variables->database = $this->database;
        try {
            $operation = (new Binding())->operation($session, $this->statement, $parameters, $this->profile, $this->literals);
            (new \MySqlMemory\Hint\Hints())->prepare($operation, $session);
            return self::capture($operation, $session);
        } finally {
            $session->variables->database = $database;
        }
    }

    /**
     * Returns protocol replies, including partial results and a terminal SQL error.
     *
     * @param list<array{int|float|string|\MySqlMemory\Evaluation\ExactDecimal|null, \MySqlMemory\Typing\Domain}> $parameters Bound values and their domains
     * @return list<Reply|SqlError>
     */
    public function run(Session $session, string $label, array $parameters): array
    {
        $gone = $session->gone();
        if ($gone !== null) {
            return [$gone];
        }
        try {
            $reply = $this->execute($session, $label, $parameters);
            return $reply instanceof Batch ? $reply->replies : [$reply];
        } catch (SqlError $error) {
            return (new Execution($session))->failed($error);
        }
    }
}
