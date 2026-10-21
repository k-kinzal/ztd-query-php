<?php

declare(strict_types=1);

namespace Fuzz\Structure\Audit\Storage;

use mysqli;
use mysqli_result;
use RuntimeException;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Waits for native asynchronous truncation after the compared INACTIVE command.
 * This establishes an empty fixture before the unchanged DROP is observed; it
 * neither retries a failed target nor changes the target's comparison contract.
 */
final class UndoState
{
    /**
     * Lets a successful fixture deactivation finish before its dependent target.
     */
    public function after(\SqlSemantics\Platform\MySql\Statement\Statement $step, mysqli $native): void
    {
        if ($step instanceof \SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterUndoTablespace && !$step->active) {
            $this->empty($native, $step->name);
        }
    }

    /**
     * @return non-empty-list<string> Every observed physical state, ending in empty
     * @throws RuntimeException When the fixture vanishes or does not become empty
     */
    public function empty(mysqli $native, Name $name): array
    {
        $history = [];
        $deadline = microtime(true) + 45;
        do {
            $result = $native->query("SELECT STATE FROM information_schema.INNODB_TABLESPACES WHERE NAME = '" . $native->real_escape_string($name->value) . "'");
            if (!$result instanceof mysqli_result) {
                throw new RuntimeException('The native undo catalog did not return rows.');
            }
            $row = $result->fetch_row();
            $result->free();
            if (!is_array($row) || !is_string($row[0])) {
                throw new RuntimeException('The native undo fixture disappeared before truncation.');
            }
            $history[] = $row[0];
            if ($row[0] === 'empty') {
                return $history;
            }
            usleep(100000);
        } while (microtime(true) < $deadline);

        throw new RuntimeException('The native undo fixture did not become empty: ' . implode(', ', $history));
    }
}
