<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation\Table;

use ZtdQuery\Exception\DuplicateKeyException;
use ZtdQuery\Exception\NotNullViolationException;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Shadow\Mutation\DataMutation;
use ZtdQuery\Shadow\Row\RowMatch;
use ZtdQuery\Shadow\Row\RowMultiset;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Replaces a table with a database-evaluated complete result set.
 *
 * @phpstan-import-type Row from TableDefinition
 */
final class SynchronizeMutation implements DataMutation
{
    /**
     * Binds the instance to what it will work from.
     *
     * @param string $tableName Table the result set stands for
     * @param ?TableDefinition $definition What that table will and will not hold, where it is known
     * @param string $sql Statement to name in anything it refuses
     * @param RowMatch $match Finds a row among rows by the key that identifies it
     * @param RowMultiset $rows Accounts for rows that repeat
     */
    public function __construct(
        private readonly string $tableName,
        private readonly ?TableDefinition $definition = null,
        private readonly string $sql = '',
        private readonly RowMatch $match = new RowMatch(),
        private readonly RowMultiset $rows = new RowMultiset(),
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @throws NotNullViolationException When a row leaves null in a column that will not take one
     * @throws DuplicateKeyException When two rows collide on a candidate key
     */
    public function apply(ShadowStore $store, array $rows): void
    {
        if ($this->definition !== null) {
            $validated = [];
            foreach ($rows as $row) {
                foreach ($this->definition->notNullColumns as $column) {
                    if (($row[$column] ?? null) === null) {
                        throw new NotNullViolationException($this->sql, $this->tableName, $column);
                    }
                }

                $conflict = $this->definition->candidateKeys()->findConflict($row, $validated);
                if ($conflict !== null) {
                    throw new DuplicateKeyException(
                        $this->sql,
                        $this->tableName,
                        $conflict->keyName,
                        $conflict->values,
                    );
                }
                $validated[] = $row;
            }
        }

        $store->set($this->tableName, $rows);
    }

    /**
     * Table name.
     *
     * @return string
     */
    public function tableName(): string
    {
        return $this->tableName;
    }

    /**
     * Count inserted, updated, and deleted rows once each.
     *
     * @param list<Row> $before
     * @param list<Row> $after
     */
    public function affectedRowCount(array $before, array $after): int
    {
        $primaryKeys = $this->definition->primaryKeys ?? [];
        if ($primaryKeys === []) {
            return max(
                count($this->rows->difference($before, $after)),
                count($this->rows->difference($after, $before)),
            );
        }

        $matchedAfter = [];
        $count = 0;
        foreach ($before as $beforeRow) {
            $afterIndex = $this->match->positionOfSameKey($after, $beforeRow, $primaryKeys, $matchedAfter);
            if ($afterIndex === null) {
                $count++;
                continue;
            }
            $matchedAfter[] = $afterIndex;
            if ($beforeRow !== $after[$afterIndex]) {
                $count++;
            }
        }

        return $count + count($after) - count($matchedAfter);
    }
}
