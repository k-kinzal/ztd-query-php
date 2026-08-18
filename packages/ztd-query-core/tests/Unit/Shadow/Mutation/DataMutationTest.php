<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Shadow\Mutation\DataMutation;
use ZtdQuery\Shadow\Mutation\DeleteMutation;
use ZtdQuery\Shadow\Mutation\InsertMutation;
use ZtdQuery\Shadow\Mutation\MultiDeleteMutation;
use ZtdQuery\Shadow\Mutation\MultiTruncateMutation;
use ZtdQuery\Shadow\Mutation\MultiUpdateMutation;
use ZtdQuery\Shadow\Mutation\ReplaceMutation;
use ZtdQuery\Shadow\Mutation\TruncateMutation;
use ZtdQuery\Shadow\Mutation\UpdateMutation;
use ZtdQuery\Shadow\Mutation\UpsertMutation;

#[CoversNothing]
final class DataMutationTest extends TestCase
{
    public function testRowChangingMutationsShareReferentialIntegrityMarker(): void
    {
        self::assertInstanceOf(DataMutation::class, new InsertMutation('items'));
        self::assertInstanceOf(DataMutation::class, new UpdateMutation('items', ['id']));
        self::assertInstanceOf(DataMutation::class, new DeleteMutation('items', ['id']));
        self::assertInstanceOf(DataMutation::class, new ReplaceMutation('items'));
        self::assertInstanceOf(DataMutation::class, new UpsertMutation('items', ['id']));
        self::assertInstanceOf(DataMutation::class, new TruncateMutation('items'));
        self::assertInstanceOf(DataMutation::class, new MultiUpdateMutation([]));
        self::assertInstanceOf(DataMutation::class, new MultiDeleteMutation([]));
        self::assertInstanceOf(DataMutation::class, new MultiTruncateMutation([]));
    }
}
