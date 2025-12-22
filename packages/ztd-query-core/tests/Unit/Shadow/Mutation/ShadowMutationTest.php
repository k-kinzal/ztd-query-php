<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Contract\MutationContractTest;
use ZtdQuery\Shadow\Mutation\DeleteMutation;
use ZtdQuery\Shadow\Mutation\InsertMutation;
use ZtdQuery\Shadow\Mutation\TruncateMutation;
use ZtdQuery\Shadow\Mutation\UpdateMutation;
use ZtdQuery\Shadow\ShadowStore;

#[CoversClass(InsertMutation::class)]
#[CoversClass(DeleteMutation::class)]
#[CoversClass(UpdateMutation::class)]
#[CoversClass(TruncateMutation::class)]
#[UsesClass(ShadowStore::class)]
final class ShadowMutationTest extends MutationContractTest
{
    protected function initialRows(): array
    {
        return [
            ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'],
            ['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com'],
            ['id' => 3, 'name' => 'Charlie', 'email' => 'charlie@example.com'],
        ];
    }

    protected function insertRows(): array
    {
        return [
            ['id' => 4, 'name' => 'Diana', 'email' => 'diana@example.com'],
            ['id' => 5, 'name' => 'Eve', 'email' => 'eve@example.com'],
        ];
    }

    protected function deleteRows(): array
    {
        return [
            ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'],
        ];
    }

    protected function updateRows(): array
    {
        return [
            ['id' => 2, 'name' => 'Bobby', 'email' => 'bobby@example.com'],
        ];
    }

    protected function primaryKeys(): array
    {
        return ['id'];
    }
}
