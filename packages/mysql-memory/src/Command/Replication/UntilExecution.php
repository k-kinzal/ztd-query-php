<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Replication;

use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Function\Special\GtidSet;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Replica\ReplicaUntil;
use SqlSemantics\Platform\MySql\Statement\Replication\Replica\UntilPoint;
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
     * Records the log-coordinate startup note and validates effective filenames.
     * GTID and gap conditions do not have a file-position restart note.
     *
     * @throws SqlError When a reached log filename has no numeric extension
     */
    public function check(?ReplicaUntil $until, Session $session): void
    {
        if ($until?->gtids !== null) {
            $this->gtids($until, $session);
        }
        if ($until === null || $until->positions === []) {
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
    /**
     * Reads a reached GTID operand, preserving sequential-mode and parse diagnostics.
     * MySQL 8.2 added parallel execution for SQL_AFTER_GTIDS; earlier releases
     * report the fallback before validating the set. Verified on 8.0 through 9.1.
     *
     * @throws SqlError When the reached operand cannot be read as a GTID set
     */
    public function gtids(ReplicaUntil $until, Session $session): ?GtidSet
    {
        if ($until->gtids === null) {
            return null;
        }
        if ($until->point === UntilPoint::AfterGtids && version_compare($session->instance->version, '8.2', '<') && (int) ($session->variables->read('replica_parallel_workers') ?? $session->variables->read('slave_parallel_workers')) > 0) {
            $session->diagnostics->note(AdministrationError::ReplicaUntilSequential, AdministrationError::ReplicaUntilSequential->message());
        }
        $text = explode("\0", $until->gtids->bytes(), 2)[0];
        $set = GtidSet::parse($text, version_compare($session->instance->version, '8.3', '>='));
        if ($set === null) {
            $error = AdministrationError::MalformedGtidSet;
            throw new SqlError($error, $error->message(substr($text, 0, 200)), following: [[AdministrationError::BadReplicaUntil->number(), AdministrationError::BadReplicaUntil->message()]]);
        }
        return $set;
    }

    /**
     * Tests a condition after repository initialization. This emulator has no
     * unapplied relay gaps; a completed GTID set also needs no applier work.
     * SQL_BEFORE_GTIDS waits for an incoming member even for an empty set.
     */
    public function complete(?ReplicaUntil $until, Session $session): bool
    {
        if ($until?->point === UntilPoint::AfterGaps) {
            return true;
        }
        if ($until?->point !== UntilPoint::AfterGtids || $until->gtids === null) {
            return false;
        }
        $tags = version_compare($session->instance->version, '8.3', '>=');
        $wanted = GtidSet::parse(explode("\0", $until->gtids->bytes(), 2)[0], $tags);
        $executed = GtidSet::parse((string) $session->variables->read('gtid_executed'), $tags);
        return $wanted !== null && $executed !== null && $wanted->within($executed);
    }

}
