<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow;

use ZtdQuery\Schema\TableDefinitionRegistry;

/**
 * Maintains transactional snapshots of all mutable shadow session state.
 */
final class ShadowTransactionManager
{
    /**
     * @var list<array{name: string|null, store: ShadowStore, registry: TableDefinitionRegistry}>
     */
    private array $frames = [];

    public function __construct(
        private readonly ShadowStore $store,
        private readonly ?TableDefinitionRegistry $registry = null,
    ) {
    }

    public function begin(): void
    {
        if ($this->frames !== []) {
            return;
        }

        $this->frames[] = $this->snapshot(null);
    }

    public function commit(): void
    {
        $this->frames = [];
    }

    public function rollBack(): void
    {
        if ($this->frames === []) {
            return;
        }

        $this->restore($this->frames[0]);
        $this->frames = [];
    }

    public function savepoint(string $name): void
    {
        $existingIndex = $this->findSavepoint($name);
        if ($existingIndex !== null) {
            $this->frames = array_slice($this->frames, 0, $existingIndex);
        }
        $this->frames[] = $this->snapshot($name);
    }

    public function rollBackTo(string $name): void
    {
        $index = $this->findSavepoint($name);
        if ($index === null) {
            return;
        }

        $this->restore($this->frames[$index]);
        $this->frames = array_slice($this->frames, 0, $index + 1);
    }

    public function release(string $name): void
    {
        $index = $this->findSavepoint($name);
        if ($index !== null) {
            $this->frames = array_slice($this->frames, 0, $index);
        }
    }

    /**
     * @return array{name: string|null, store: ShadowStore, registry: TableDefinitionRegistry}
     */
    private function snapshot(?string $name): array
    {
        return [
            'name' => $name,
            'store' => $this->store->snapshot(),
            'registry' => $this->registry?->snapshot() ?? new TableDefinitionRegistry(),
        ];
    }

    /**
     * @param array{name: string|null, store: ShadowStore, registry: TableDefinitionRegistry} $frame
     */
    private function restore(array $frame): void
    {
        $this->store->restore($frame['store']);
        $this->registry?->restore($frame['registry']);
    }

    private function findSavepoint(string $name): ?int
    {
        for ($index = count($this->frames) - 1; $index >= 0; $index--) {
            if ($this->frames[$index]['name'] === $name) {
                return $index;
            }
        }

        return null;
    }
}
