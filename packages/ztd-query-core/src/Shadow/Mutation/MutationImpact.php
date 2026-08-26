<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Rewrite\AffectedRowsMode;

/**
 * Derives observable execution metadata from a shadow-state transition.
 *
 * @phpstan-import-type Row from StatementInterface
 */
final class MutationImpact
{
    /**
     * @param list<Row> $before
     * @param list<Row> $input
     * @param list<Row> $after
     */
    public function __construct(
        private readonly ShadowMutation $mutation,
        private readonly array $before,
        private readonly array $input,
        private readonly array $after,
    ) {
    }

    /**
     * Affected row count.
     *
     * @param AffectedRowsMode $mode
     * @return int
     */
    public function affectedRowCount(AffectedRowsMode $mode): int
    {
        if ($mode === AffectedRowsMode::None) {
            return 0;
        }
        if ($mode === AffectedRowsMode::Matched && $this->mutation instanceof UpsertMutation) {
            return count($this->mutation->resultRows());
        }
        if ($mode === AffectedRowsMode::Matched && $this->mutation instanceof UpdateMutation) {
            return count($this->input);
        }
        if ($mode === AffectedRowsMode::Changed && $this->mutation instanceof SynchronizeMutation) {
            return $this->mutation->affectedRowCount($this->before, $this->after);
        }

        return max(
            count($this->difference($this->before, $this->after)),
            count($this->difference($this->after, $this->before)),
        );
    }

    /**
     * @return list<Row>
     */
    public function returningRows(): array
    {
        if ($this->mutation instanceof UpsertMutation) {
            return $this->clean($this->mutation->resultRows());
        }
        if ($this->mutation instanceof UpdateMutation || $this->mutation instanceof DeleteMutation) {
            return $this->clean($this->input);
        }

        $added = $this->difference($this->after, $this->before);
        if ($added !== []) {
            return $added;
        }

        return [];
    }

    /**
     * Reports whether insert like.
     *
     * @return bool
     */
    public function isInsertLike(): bool
    {
        return $this->mutation instanceof InsertMutation
            || $this->mutation instanceof ReplaceMutation
            || $this->mutation instanceof SynchronizeMutation
            || $this->mutation instanceof UpsertMutation;
    }

    /**
     * @param list<Row> $left
     * @param list<Row> $right
     * @return list<Row>
     */
    private function difference(array $left, array $right): array
    {
        $remaining = $right;
        $difference = [];
        foreach ($left as $row) {
            $match = null;
            foreach ($remaining as $index => $candidate) {
                if ($this->rowsEqual($row, $candidate)) {
                    $match = $index;
                }
            }
            if ($match === null) {
                $difference[] = $row;
                continue;
            }
            unset($remaining[$match]);
        }

        return $difference;
    }

    /**
     * @param Row $left
     * @param Row $right
     */
    private function rowsEqual(array $left, array $right): bool
    {
        if (count($left) !== count($right)) {
            return false;
        }
        foreach ($left as $column => $value) {
            if (!array_key_exists($column, $right) || $right[$column] !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<Row> $rows
     * @return list<Row>
     */
    private function clean(array $rows): array
    {
        $identity = new MutationRowIdentity();

        return array_map($identity->strip(...), $rows);
    }
}
