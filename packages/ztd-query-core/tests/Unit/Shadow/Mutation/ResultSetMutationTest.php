<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\Mutation\CreateTableAsSelectMutation;
use ZtdQuery\Shadow\Mutation\ResultSetMutation;

#[CoversNothing]
final class ResultSetMutationTest extends TestCase
{
    public function testCreateTableAsSelectImplementsResultSetContract(): void
    {
        $mutation = new CreateTableAsSelectMutation('copy', [], new TableDefinitionRegistry());

        self::assertInstanceOf(ResultSetMutation::class, $mutation);
    }
}
