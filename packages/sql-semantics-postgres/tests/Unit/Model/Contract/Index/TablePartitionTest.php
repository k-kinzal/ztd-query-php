<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Contract\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\TablePartition;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\TablePartitionRole;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;

#[CoversClass(TablePartition::class)]
#[Small]
final class TablePartitionTest extends TestCase
{
    public function testKeepsTheResolvedIdentityAndItsRole(): void
    {
        $table = new Name('leaf');
        $schema = new Name('app');
        $declaration = new TablePartition($schema, $table, TablePartitionRole::Leaf);

        self::assertSame($table, $declaration->table);
        self::assertSame($schema, $declaration->schema);
        self::assertSame(TablePartitionRole::Leaf, $declaration->role);
    }
}
