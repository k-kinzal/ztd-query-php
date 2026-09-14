<?php

declare(strict_types=1);

namespace Tests\Fake;

use ZtdQuery\Sql\TransactionTarget;

/**
 * A transaction target that records what it was asked for.
 *
 * Nothing here keeps transaction state, so a test can check that a statement
 * asked for the one thing it stands for and handed over the name it carried.
 */
final class RecordingTransactionTarget implements TransactionTarget
{
    /**
     * @var list<string> What this target was asked for, in order
     */
    public array $asked = [];

    /**
     * Records that a transaction was opened.
     */
    public function begin(): void
    {
        $this->asked[] = 'begin';
    }

    /**
     * Records that the transaction was kept.
     */
    public function commit(): void
    {
        $this->asked[] = 'commit';
    }

    /**
     * Records that the transaction was undone.
     */
    public function rollBack(): void
    {
        $this->asked[] = 'rollBack';
    }

    /**
     * Records that a point was marked.
     *
     * @param string $name Name the point is marked with
     */
    public function savepoint(string $name): void
    {
        $this->asked[] = "savepoint {$name}";
    }

    /**
     * Records that the transaction was brought back to a point.
     *
     * @param string $name Name the point was marked with
     */
    public function rollBackTo(string $name): void
    {
        $this->asked[] = "rollBackTo {$name}";
    }

    /**
     * Records that a point was forgotten.
     *
     * @param string $name Name the point was marked with
     */
    public function release(string $name): void
    {
        $this->asked[] = "release {$name}";
    }
}
