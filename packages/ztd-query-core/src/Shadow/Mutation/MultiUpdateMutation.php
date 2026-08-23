<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

use ZtdQuery\Shadow\ShadowStore;

/**
 * Applies multi-table UPDATE operation to the shadow store.
 * This mutation handles UPDATE statements that target multiple tables.
 */
final class MultiUpdateMutation implements DataMutation
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
            $updates = [];
            foreach ($rows as $row) {
                $values = $codec->values($row, $targetIndex, $target->columns());
                $identity = $codec->identity($row, $targetIndex, $target->primaryKeys());
                if ($values !== null && $identity !== null) {
                    $updates[] = ['row' => $values, 'identity' => $identity];
                }
            }
            $store->updateIdentified(
                $target->tableName(),
                $updates,
                $target->primaryKeys(),
            );
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
