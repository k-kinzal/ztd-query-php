<?php

declare(strict_types=1);

namespace Fuzz\Session;

/**
 * Plain-array model of virtual rows and transactional schema, independent of ZTD classes.
 */
final class ReferenceState
{
    /**
     * @var array<string, array<int, array{id: int, value: mixed}>>
     */
    public array $tables = ['items' => [], 'other' => []];
    /**
     * @var list<array{name: string|null, tables: array<string, array<int, array{id: int, value: mixed}>>}>
     */
    public array $checkpoints = [];

    /**
     * Advance the plain-array model or return the required rejection category.
     */
    public function apply(int $operation, string $table, int $id, mixed $value, string $name): ?string
    {
        if ($operation === 0) {
            if (isset($this->tables[$table][$id])) {
                return 'unique';
            }
            $this->tables[$table][$id] = ['id' => $id, 'value' => $value];
        } elseif ($operation === 1 && isset($this->tables[$table][$id])) {
            $this->tables[$table][$id]['value'] = $value;
        } elseif ($operation === 2) {
            unset($this->tables[$table][$id]);
        } elseif ($operation === 9) {
            $this->tables['temporary'] ??= [];
        } elseif ($operation === 10) {
            unset($this->tables['temporary']);
        } elseif ($operation === 11 || $operation === 12) {
            return $operation === 11 ? 'unique' : 'not-null';
        } elseif ($operation >= 3 && $operation <= 8) {
            $this->transaction($operation, $name);
        }
        return null;
    }

    /**
     * Apply transaction and savepoint semantics to model checkpoints.
     */
    public function transaction(int $operation, string $name): void
    {
        if ($operation === 3 && $this->checkpoints === []) {
            $this->checkpoints[] = ['name' => null, 'tables' => $this->tables];
        } elseif ($operation === 4) {
            $this->checkpoints = [];
        } elseif ($operation === 5 && $this->checkpoints !== []) {
            $this->tables = $this->checkpoints[0]['tables'];
            $this->checkpoints = [];
        } elseif ($operation >= 6) {
            $position = array_search($name, array_column($this->checkpoints, 'name'), true);
            if ($position !== false) {
                if ($operation === 7) {
                    $this->tables = $this->checkpoints[$position]['tables'];
                }
                $this->checkpoints = array_slice($this->checkpoints, 0, $position + ($operation === 7 ? 1 : 0));
            }
            if ($operation === 6) {
                $this->checkpoints[] = ['name' => $name, 'tables' => $this->tables];
            }
        }
    }
}
