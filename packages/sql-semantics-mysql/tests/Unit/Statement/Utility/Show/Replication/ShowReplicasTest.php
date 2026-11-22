<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowReplicas;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ShowReplicas::class)]
#[Medium]
final class ShowReplicasTest extends TestCase
{
    public function testDeriveStatementNamesTheColumnsAfterTheSpelling(): void
    {
        $show = (new Inspection())->inspect('SHOW REPLICAS');
        self::assertInstanceOf(ShowReplicas::class, $show->statement);
        self::assertSame('Replica_UUID', $show->field(4)->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW REPLICAS', (new Inspection())->inspect('SHOW REPLICAS')->toString());
    }
}
