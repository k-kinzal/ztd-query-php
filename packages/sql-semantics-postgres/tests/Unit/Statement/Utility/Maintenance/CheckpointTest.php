<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Checkpoint;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(Checkpoint::class)]
#[Medium]
final class CheckpointTest extends TestCase
{
    public function testDeriveStatementRecordsNothing(): void
    {
        $operation = (new Inspection())->inspect('CHECKPOINT');
        self::assertSame([[], null], [$operation->facts->diagnostics, $operation->facts->output]);
    }

    public function testRenderWritesTheKeyword(): void
    {
        self::assertSame('CHECKPOINT', (new Inspection())->inspect('checkpoint')->toString());
    }
}
