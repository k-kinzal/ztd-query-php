<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Routine;

use MySqlMemory\Dictionary\Routine;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;

/**
 * Reports explicit collation notices when a session first loads a routine definition.
 *
 * A routine DDL invalidates these session entries; table flushes do not. The definition
 * supplies typed collation occurrences, without reparsing stored display text.
 * Observed with repeated calls, ALTER PROCEDURE and independent connections on MySQL 8.4.
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
        foreach ((new Walker())->find($routine->statement, CollationName::class) as $collation) {
            $notice = $collation->deprecation($session->settings()->release());
            if ($notice !== null) {
                $session->diagnostics->warning($notice->code(), $notice->message());
            }
        }
    }
}
