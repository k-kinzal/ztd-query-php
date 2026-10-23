<?php

declare(strict_types=1);

namespace MySqlMemory\Hint;

use Closure;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlSemantics\Contract\GrammarRelease;

/**
 * Reports typed trigger hints when table preparation loads stored definitions.
 * Resource placement follows preparation errors and is reported once per load
 * phase. MySQL 9.1 delays loading until a write; earlier releases also load on
 * reads. Observed through SELECT, UPDATE, trigger DDL and table flush histories.
 *
 * @visibility MySqlMemory
 */
final class TriggerNotices
{
    /**
     * Whether a loaded definition contains an unsupported resource-group hint.
     */
    public bool $resource = false;

    /**
     * Isolates one preparation and appends its resource notice after any error.
     *
     * @param Closure(): void $prepare The normal semantic preparation
     * @throws SqlError When preparation reports a SQL condition
     */
    public function during(Closure $prepare, Session $session): void
    {
        $this->resource = $this->resource || $session->preparation->replayedTriggerResource;
        $session->preparation->replayedTriggerResource = false;
        $previous = $session->preparation->triggerNotices;
        $session->preparation->triggerNotices = $this;
        try {
            $prepare();
        } catch (SqlError $error) {
            if (!$this->resource) {
                throw $error;
            }
            if (!$error->recorded) {
                $session->diagnostics->error($error->getCode(), $error->getMessage(), $error->signalled);
            }
            foreach ($error->following as [$code, $message]) {
                $session->diagnostics->error($code, $message);
            }
            throw new SqlError($error->error, $error->getMessage(), $error, [], $error->signalled, $error->getCode(), true);
        } finally {
            $session->preparation->triggerNotices = $previous;
            $this->report($session);
        }
    }

    /**
     * Loads the table's definition list once per connection and cache lifetime.
     */
    public function load(string $schema, string $table, Session $session, bool $write = false): void
    {
        if ($session->settings()->release() === GrammarRelease::MySql910 && !$write) {
            return;
        }
        $dictionary = $session->instance->dictionary;
        $triggers = array_values(array_filter($dictionary->schema($schema)->triggers ?? [], static fn ($trigger): bool => $trigger->table === $table));
        if (!$dictionary->cache->triggers->load($session, $schema, $table, $triggers)) {
            return;
        }
        foreach ($triggers as $trigger) {
            foreach ((new ProgramHints())->warnings($trigger->statement, $session) as [$code, $message]) {
                if ($code === StatementError::HintNotSupported->number()) {
                    $this->resource = true;
                    $session->preparation->triggerResourceLoads++;
                } else {
                    $session->diagnostics->warning($code, $message);
                }
            }
        }
        if ($session->preparation->triggerNotices === null) {
            $this->report($session);
        }
    }

    /**
     * Publishes the deferred condition after resolution, then consumes it.
     */
    public function report(Session $session): void
    {
        if ($this->resource) {
            $session->diagnostics->warning(StatementError::HintNotSupported, StatementError::HintNotSupported->message('Subquery or Stored procedure or Trigger'));
            $this->resource = false;
        }
    }
}
