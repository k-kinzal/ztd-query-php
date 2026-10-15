<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Routine;

use MySqlMemory\Dictionary\Routine;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;

/**
 * Reports collation and function-name notices when a session first loads a routine definition.
 *
 * A routine DDL invalidates these session entries; table flushes do not. The definition
 * supplies typed collation occurrences and function names, without reparsing stored display text.
 * Loading a definition with notices replaces preceding conditions from MySQL 5.7 onward;
 * a silent load leaves them intact. MySQL 5.6 accumulates notices within the statement.
 * Observed with repeated calls, ALTER PROCEDURE and independent connections on MySQL 5.6–9.1.
 *
 * @visibility MySqlMemory
 */
final class LoadNotices
{
    /**
     * Loads a user definition's notices once per session and dictionary generation.
     */
    public function record(Routine $routine, Session $session): void
    {
        $prepared = $session->preparation;
        $generation = $session->instance->dictionary->routineGeneration;
        if ($prepared->routineGeneration !== $generation) {
            $prepared->routineGeneration = $generation;
            $prepared->routines = [];
        }
        $identity = spl_object_id($routine);
        if (isset($prepared->routines[$identity]) || $routine->statement === null) {
            return;
        }
        $prepared->routines[$identity] = true;
        $stale = $session->diagnostics->stale;
        if ($session->settings()->release() !== \SqlSemantics\Contract\GrammarRelease::MySql5651) {
            $session->diagnostics->stale = true;
        }
        foreach ((new Walker())->find($routine->statement, CollationName::class) as $collation) {
            $notice = $collation->deprecation($session->settings()->release());
            if ($notice !== null) {
                $session->diagnostics->warning($notice->code(), $notice->message());
            }
        }
        if ($routine->statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction && $routine->statement->nameCollision($session->settings()->release())) {
            $code = \MySqlMemory\Error\Family\AdministrationError::NativeFunctionName;
            $session->diagnostics->note($code, $code->message($routine->name));
        }
        if ($session->diagnostics->stale) {
            $session->diagnostics->stale = $stale;
        }
    }
}
