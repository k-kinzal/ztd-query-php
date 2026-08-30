<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

use ZtdQuery\Rewrite\AffectedRowsMode;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Shadow\Row\RowMultiset;

/**
 * Derives observable execution metadata from a shadow-state transition.
 *
 * @phpstan-import-type Row from TableDefinition
 */
final class MutationImpact
{
    /**
     * @param ShadowMutation $mutation The statement whose effect this reports
     * @param list<Row> $before The table as it stood
     * @param list<Row> $input The rows the statement was given
     * @param list<Row> $after The table as it stands now
     * @param RowMultiset $rows Accounts for rows that repeat
     * @param MutationRowIdentity $identity Takes the carried names back off a row
     */
    public function __construct(
        private readonly ShadowMutation $mutation,
        private readonly array $before,
        private readonly array $input,
        private readonly array $after,
        private readonly RowMultiset $rows = new RowMultiset(),
        private readonly MutationRowIdentity $identity = new MutationRowIdentity(),
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
            count($this->rows->difference($this->before, $this->after)),
            count($this->rows->difference($this->after, $this->before)),
        );
    }

    /**
     * @return list<Row>
     */
    public function returningRows(): array
    {
        if ($this->mutation instanceof UpsertMutation) {
            return $this->identity->stripAll($this->mutation->resultRows());
        }
        if ($this->mutation instanceof UpdateMutation || $this->mutation instanceof DeleteMutation) {
            return $this->identity->stripAll($this->input);
        }

        $added = $this->rows->difference($this->after, $this->before);
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
}
