<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Transaction\Postgres;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Transaction\Postgres\Rollback;

#[CoversClass(Rollback::class)]
#[Small]
final class RollbackTest extends TestCase
{
    #[TestWith([false, 'ROLLBACK'])]
    #[TestWith([true, 'ROLLBACK AND CHAIN'])]
    public function testToStringPreservesTheChainingRequest(bool $chain, string $expected): void
    {
        self::assertSame($expected, (new Rollback($chain))->toString());
    }
}
