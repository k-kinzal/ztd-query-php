<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

use ZtdQuery\Shadow\ShadowStore;

/**
 * Applies multi-table DELETE operation to the shadow store.
 * This mutation handles DELETE statements that target multiple tables.
 */
final class MultiDeleteMutation implements DataMutation
{
    /** @var list<MultiTableMutationTarget> */
    private array $targets;

    /**
     * Primary table name (first target table).
     *
     * @var string
     */
    private string $primaryTable;

    /** @param list<MultiTableMutationTarget> $targets */
    public function __construct(array $targets)
    {
        $this->targets = $targets;
        $this->primaryTable = ($targets[0] ?? null)?->tableName() ?? '';
    }

    /**
     * {@inheritDoc}
     */
    public function apply(ShadowStore $store, array $rows): void
    {
        $codec = new MultiTableMutationRow();
        foreach ($this->targets as $targetIndex => $target) {
            $matchColumns = $target->matchColumns();
            $deletedRows = [];
            foreach ($rows as $row) {
                $values = $codec->values($row, $targetIndex, $matchColumns);
                if ($values !== null) {
                    $deletedRows[] = $values;
                }
            }
            $store->delete($target->tableName(), $deletedRows, $matchColumns);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function tableName(): string
    {
        return $this->primaryTable;
    }

    /**
     * Get all target table names.
     *
     * @return array<int, string>
     */
    public function tableNames(): array
    {
        return array_map(
            static fn (MultiTableMutationTarget $target): string => $target->tableName(),
            $this->targets,
        );
    }
}
