<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Statement;

use Closure;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Result\Batch;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use SqlSemantics\Statement\Node;

/**
 * The runtime lifetime shared by commands after their SQL-specific preparation.
 *
 * Execution owns a statement journal and active table scope, independently of
 * the SQL input or generated-value producer. Only SQL errors become protocol
 * replies; implementation failures unwind the same scope and escape unchanged.
 *
 * @visibility MySqlMemory
 */
final class Lifecycle
{
    /**
     * @param Session $session The session running the command
     */
    public function __construct(public readonly Session $session)
    {
    }

    /**
     * Initializes the diagnostic boundary before the SQL-specific retention policy.
     */
    public function start(): void
    {
        $session = $this->session;
        $session->variables->setByFunction = false;
        $session->interrupted = false;
        $session->diagnostics->previous = [$session->diagnostics->raised(), $session->diagnostics->errors()];
        $session->diagnostics->limit = $session->variables->count('max_error_count', 1024);
    }

    /**
     * Executes a command, unwinding its own statement journal exactly once.
     *
     * A successful contained statement leaves its writes in the enclosing journal.
     * Every exceptional exit unwinds this statement, including an implementation
     * failure. The protocol boundary separately converts SQL errors to replies.
     *
     * @param Closure(): Reply $execute The prepared command, consuming semantic values
     * @param bool $contained Whether successful writes belong to the enclosing statement
     * @throws SqlError When the command or stored-program table access fails
     */
    public function run(Node $statement, Closure $execute, bool $contained = true): Reply
    {
        $session = $this->session;
        if ($session->program?->contained === true) {
            $session->running->check($statement, $session->variables->database);
        }
        $used = $session->running->used($statement, $session->variables->database);
        $temporaries = $session->instance->dictionary->temporaries;
        $session->transaction->statements->begin($contained);
        $session->instance->dictionary->temporaries = $session->temporaries;
        $session->running->using[] = $used;
        $succeeded = false;
        try {
            $reply = $execute();
            $succeeded = true;
        } finally {
            try {
                if ($succeeded) {
                    $session->transaction->statements->end($contained);
                } else {
                    $session->variables->rowCount = -1;
                    $session->transaction->statements->abort();
                }
            } finally {
                array_pop($session->running->using);
                $session->instance->dictionary->temporaries = $temporaries;
            }
        }
        $last = $reply instanceof Batch ? ($reply->replies[count($reply->replies) - 1] ?? null) : $reply;
        $session->variables->rowCount = $last instanceof Completion ? $last->affectedRows : -1;
        if ($session->program === null && $session->running->embeddedResults !== []) {
            return new Batch([...$session->running->takeEmbedded(), $reply]);
        }
        return $reply;
    }
}
