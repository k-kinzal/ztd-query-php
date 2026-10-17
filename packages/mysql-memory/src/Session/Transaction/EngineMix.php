<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Transaction;

use MySqlMemory\Command\Show\Server\ServerCatalog;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Error\Family\TransactionError;
use MySqlMemory\Session\Transaction;

/**
 * The engines of the tables a transaction updated, and the warning MySQL 9.0 on gives once a transaction updates tables of InnoDB and of another engine both.
 *
 * Source: https://dev.mysql.com/doc/refman/9.1/en/mysql-nutshell.html.
 *
 * @visibility MySqlMemory
 */
final class EngineMix
{
    /**
     * @var array<int, StoredTable> The first table the transaction updated of each kind, by 1 for InnoDB and 0 for another engine
     */
    public array $updated = [];

    /**
     * Whether the transaction warned that it updates tables of an InnoDB and of another engine.
     */
    public bool $combined = false;

    /**
     * @param Transaction $transaction The transaction that updates the tables
     */
    public function __construct(public readonly Transaction $transaction)
    {
    }

    /**
     * Records the table a change updates, and from MySQL 9.0 on warns, once in a transaction, when it updates tables of InnoDB and of another engine both, naming the table updated now first.
     */
    public function combine(StoredTable $table): void
    {
        $kind = Transaction::transactional($table) ? 1 : 0;
        $this->updated[$kind] ??= $table;
        $other = $this->updated[1 - $kind] ?? null;
        if ($other === null || $this->combined || version_compare($this->transaction->variables->instance->version ?? '8.4.7', '9.0.0', '<')) {
            return;
        }
        $this->combined = true;
        $first = $table->definition;
        $second = $other->definition;
        $engines = ServerCatalog::shared();
        $named = $engines->engine($first->engine) ?? $first->engine;
        $otherNamed = $engines->engine($second->engine) ?? $second->engine;
        $this->transaction->diagnostics?->warning(TransactionError::CombinedEngines, TransactionError::CombinedEngines->message($named, $otherNamed, $named, $first->schema . '.' . $first->name, $otherNamed, $second->schema . '.' . $second->name));
    }
}
