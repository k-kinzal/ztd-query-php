<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Partition\PartitionIndexReference;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Partition\PartitionRebuildKind;

#[CoversClass(PartitionIndexReference::class)]
#[Small]
final class PartitionIndexReferenceTest extends TestCase
{
    public function testQuotedUsesTheIndexNameAndPreservesTheOwnerRole(): void
    {
        $index = new Identifier('date."lookup"');
        $owner = new Identifier('items');
        $target = new PartitionIndexReference(new Identifier('billing'), $index, $owner, PartitionRebuildKind::Branch);

        self::assertSame('"billing"."date.""lookup"""', $target->quoted());
        self::assertSame($owner, $target->table);
        self::assertSame(PartitionRebuildKind::Branch, $target->kind);
    }
}
