<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Replication;

use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Replica\ReplicaUntil;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind;

/**
 * Validates retained UNTIL log operands when replica startup reaches them.
 *
 * Receiver configuration precedes these checks when the receiver is requested.
 * Applier-only startup reaches them before repository initialization. Observed
 * through SQL on MySQL 8.0.44, 8.4.7 and 9.1.0.
 *
 * @visibility MySqlMemory
 */
final class UntilExecution
{
    /**
     * Records the startup note and validates effective log filenames.
     *
     * @throws SqlError When a reached log filename has no numeric extension
     */
    public function check(?ReplicaUntil $until, Session $session): void
    {
        if ($until === null) {
            return;
        }
        $session->diagnostics->note(AdministrationError::ReplicaUntilRestart, AdministrationError::ReplicaUntilRestart->message());
        $files = [];
        foreach ($until->positions as $position) {
            if (in_array($position->kind, [SourceOptionKind::LogFile, SourceOptionKind::RelayLogFile], true) && $position->value instanceof Text) {
                $files[$position->kind->value] = explode("\0", $position->value->bytes(), 2)[0];
            }
        }
        foreach ($files as $file) {
            if (preg_match('/\.[0-9]+\z/', $file) !== 1) {
                throw AdministrationError::BadReplicaUntil->error();
            }
        }
    }
}
