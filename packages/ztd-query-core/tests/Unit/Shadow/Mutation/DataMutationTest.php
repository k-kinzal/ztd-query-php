<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CascadingShop;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Shadow\Mutation\CreateTableMutation;
use ZtdQuery\Shadow\Mutation\DeleteMutation;
use ZtdQuery\Shadow\Mutation\InsertMutation;
use ZtdQuery\Shadow\Mutation\MultiDeleteMutation;
use ZtdQuery\Shadow\Mutation\MultiTruncateMutation;
use ZtdQuery\Shadow\Mutation\MultiUpdateMutation;
use ZtdQuery\Shadow\Mutation\ReplaceMutation;
use ZtdQuery\Shadow\Mutation\ShadowMutation;
use ZtdQuery\Shadow\Mutation\SynchronizeMutation;
use ZtdQuery\Shadow\Mutation\TruncateMutation;
use ZtdQuery\Shadow\Mutation\UpdateMutation;
use ZtdQuery\Shadow\Mutation\UpsertMutation;
use ZtdQuery\Shadow\ReferentialIntegrityEnforcer;

#[CoversNothing]
final class DataMutationTest extends TestCase
{
    #[DataProvider('providerRowChangingMutation')]
    public function testAMutationThatChangesRowsCarriesItsConsequencesToTheChildren(
        ShadowMutation $mutation,
    ): void {
        $registry = CascadingShop::registry();
        $before = CascadingShop::shadow();
        $after = $before->snapshot();
        $after->set('parents', []);

        (new ReferentialIntegrityEnforcer($registry))->synchronize($before, $after, $mutation, [], 'SQL');

        self::assertSame([], $after->get('children'));
    }

    /**
     * @return iterable<string, array{ShadowMutation}>
     */
    public static function providerRowChangingMutation(): iterable
    {
        yield 'insert' => [new InsertMutation('parents')];
        yield 'update' => [new UpdateMutation('parents', ['id'])];
        yield 'delete' => [new DeleteMutation('parents', ['id'])];
        yield 'replace' => [new ReplaceMutation('parents')];
        yield 'upsert' => [new UpsertMutation('parents', ['id'])];
        yield 'truncate' => [new TruncateMutation('parents')];
        yield 'multi update' => [new MultiUpdateMutation([])];
        yield 'multi delete' => [new MultiDeleteMutation([])];
        yield 'multi truncate' => [new MultiTruncateMutation([])];
        yield 'synchronize' => [new SynchronizeMutation('parents')];
    }

    public function testAMutationThatChangesNoRowsCarriesNothingToTheChildren(): void
    {
        $registry = CascadingShop::registry();
        $before = CascadingShop::shadow();
        $after = $before->snapshot();
        $after->set('parents', []);
        $mutation = new CreateTableMutation(
            'extra',
            new TableDefinition(['id'], [], ['id'], [], []),
            $registry,
            'CREATE TABLE extra (id INT)',
        );

        (new ReferentialIntegrityEnforcer($registry))->synchronize($before, $after, $mutation, [], 'SQL');

        self::assertSame([['id' => 10, 'parent_id' => 1]], $after->get('children'));
    }
}
