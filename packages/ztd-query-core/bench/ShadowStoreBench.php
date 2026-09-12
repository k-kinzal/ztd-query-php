<?php

declare(strict_types=1);

namespace Bench;

use PhpBench\Attributes as Bench;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Measures primary-key row changes and snapshot restoration at fixed table sizes.
 */
final class ShadowStoreBench
{
    private ShadowStore $store;

    /**
     * @var list<array{id: int, name: string}>
     */
    private array $updates;

    /**
     * @param array{rows: int} $params
     */
    public function setUp(array $params): void
    {
        $this->store = new ShadowStore();
        $rows = [];
        for ($id = 1; $id <= $params['rows']; $id++) {
            $rows[] = ['id' => $id, 'name' => 'before'];
        }
        $this->store->set('users', $rows);
        $this->updates = [['id' => $params['rows'], 'name' => 'after']];
    }

    /**
     * @return array<string, array{rows: int}>
     */
    public function rowCounts(): array
    {
        return ['small' => ['rows' => 100], 'large' => ['rows' => 1000]];
    }

    /**
     * Applies a row change and restores the original table for the next revolution.
     */
    #[Bench\BeforeMethods('setUp')]
    #[Bench\ParamProviders('rowCounts')]
    #[Bench\Revs(1000)]
    public function benchUpdateAndRestore(): void
    {
        $snapshot = $this->store->snapshot();
        $this->store->update('users', $this->updates, ['id']);
        $this->store->restore($snapshot);
    }

    /**
     * Applies a row change and restores the original table for the next revolution.
     */
    #[Bench\BeforeMethods('setUp')]
    #[Bench\ParamProviders('rowCounts')]
    #[Bench\Revs(1000)]
    public function benchDeleteAndRestore(): void
    {
        $snapshot = $this->store->snapshot();
        $this->store->delete('users', $this->updates, ['id']);
        $this->store->restore($snapshot);
    }
}
