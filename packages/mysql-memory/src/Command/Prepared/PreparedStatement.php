<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Prepared;

use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Result\Batch;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\Session;
use MySqlMemory\Session\Statement\Binding;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Statement;

/**
 * A prepared semantic statement with its definition-time name and literal context.
 *
 * Executions reuse this exact structure. Current declarations and parameters are
 * resolved again; source text is only an observable request label. SQL modes and
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
     */
    public function __construct(public readonly Statement $statement, public readonly LanguageProfile $profile, public readonly Settings $literals, public readonly string $database, public readonly ?string $engine = null)
    {
    }

    /**
     * Captures the definition without retaining the operation's source map or parser.
     */
    public static function capture(Operation $operation, Session $session): self
    {
        $engine = $operation->statement instanceof \SqlSemantics\Platform\MySql\Statement\Table\CreateTable && array_filter($operation->statement->options, static fn ($option): bool => $option instanceof \SqlSemantics\Platform\MySql\Statement\Table\Option\EngineOption) !== [] ? (new \MySqlMemory\Command\Definition\StorageOptions())->engine($operation->statement, $session->variables) : null;
        return new self($operation->statement, $operation->profile(), Settings::of($operation->context), $session->variables->database, $engine);
    }

    /**
     * Executes the original structure under a fresh declaration and parameter snapshot.
     *
     * @param list<array{int|float|string|null, \MySqlMemory\Typing\Domain}> $parameters Bound values and their domains
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
        $session->variables->preparedEngines = $this->statement instanceof \SqlSemantics\Platform\MySql\Statement\Table\CreateTable ? [spl_object_id($this->statement) => $this->engine] : [];
        $session->variables->database = $this->database;
        $session->replayed = true;
        $session->text = $label;
        $session->hinted = [];
        $session->dots = [];
        try {
            $operation = (new Binding())->operation($session, $this->statement, $parameters, $this->profile, $this->literals);
            return (new Execution($session))->perform($operation, (new Dispatcher())->command($this->statement), $parameters);
        } finally {
            $session->variables->preparedEngines = $engines;
            $session->variables->database = $database;
            $session->replayed = $replayed;
        }
    }

    /**
     * Returns protocol replies, including partial results and a terminal SQL error.
     *
     * @param list<array{int|float|string|null, \MySqlMemory\Typing\Domain}> $parameters Bound values and their domains
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
