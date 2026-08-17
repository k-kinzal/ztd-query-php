<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

use ZtdQuery\Rewrite\AffectedRowsMode;

/**
 * Derives observable execution metadata from a shadow-state transition.
 */
final class MutationImpact
{
    /**
     * @param array<int, array<string, mixed>> $before
     * @param array<int, array<string, mixed>> $input
     * @param array<int, array<string, mixed>> $after
     */
    public function __construct(
        private readonly ShadowMutation $mutation,
        private readonly array $before,
        private readonly array $input,
        private readonly array $after,
    ) {
    }

    public function affectedRowCount(AffectedRowsMode $mode): int
    {
        if ($mode === AffectedRowsMode::Matched && $this->mutation instanceof UpdateMutation) {
            return count($this->input);
        }

        return max(
            count($this->difference($this->before, $this->after)),
            count($this->difference($this->after, $this->before)),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function returningRows(): array
    {
        if ($this->mutation instanceof UpdateMutation || $this->mutation instanceof DeleteMutation) {
            return $this->clean($this->input);
        }

        $added = $this->difference($this->after, $this->before);
        if ($added !== []) {
            return $added;
        }

        if ($this->mutation instanceof UpsertMutation) {
            return $this->clean($this->input);
        }

        return [];
    }

    public function isInsertLike(): bool
    {
        return $this->mutation instanceof InsertMutation
            || $this->mutation instanceof ReplaceMutation
            || $this->mutation instanceof UpsertMutation;
    }

    /**
     * @param array<int, array<string, mixed>> $left
     * @param array<int, array<string, mixed>> $right
     * @return array<int, array<string, mixed>>
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
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
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
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function clean(array $rows): array
    {
        $identity = new MutationRowIdentity();

        return array_map($identity->strip(...), $rows);
    }
}
